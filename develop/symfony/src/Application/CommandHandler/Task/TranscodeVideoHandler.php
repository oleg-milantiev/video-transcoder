<?php

namespace App\Application\CommandHandler\Task;

use App\Application\Command\Task\StartTaskScheduler;
use App\Application\Command\Task\TranscodeVideo;
use App\Application\Event\TranscodeVideoFail;
use App\Application\Event\TranscodeVideoStart;
use App\Application\Event\TranscodeVideoSuccess;
use App\Application\Service\Task\TranscodeProcessService;
use App\Application\Service\Task\TranscodeTaskPreparationService;
use App\Application\Service\Task\TranscodeTaskFinalizationService;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Infrastructure\Task\TaskCancellationTrigger;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Lock\LockFactory;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class TranscodeVideoHandler
{
    private const int TASK_MUTEX_TTL = 7200;

    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
        #[Autowire(service: 'messenger.bus.event')]
        private MessageBusInterface $eventBus,
        private TaskRepositoryInterface $taskRepository,
        private VideoRepositoryInterface $videoRepository,
        private LoggerInterface $logger,
        private LockFactory $lockFactory,
        private TaskCancellationTrigger $cancellationTrigger,
        private TranscodeProcessService $transcodeProcessService,
        private TranscodeTaskPreparationService $transcodeTaskPreparationService,
        private TranscodeTaskFinalizationService $transcodeTaskFinalizationService,
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function __invoke(TranscodeVideo $command): void
    {
        $scheduledTask = $command->scheduledTask;
        $this->eventBus->dispatch(new TranscodeVideoStart(
            taskId: $scheduledTask->taskId,
            userId: $scheduledTask->userId,
            videoId: $scheduledTask->videoId->toRfc4122(),
        ));

        $task = $this->taskRepository->findByIdFresh($scheduledTask->taskId);

        if (!$task) {
            $this->eventBus->dispatch(new TranscodeVideoFail('Scheduled task not found for transcoding', $scheduledTask->taskId));
            $this->logger->error('Scheduled task not found for transcoding', ['taskId' => $scheduledTask->taskId]);
            return;
        }

        $lock = $this->lockFactory->createLock(sprintf('transcode-task:%d', $scheduledTask->taskId), self::TASK_MUTEX_TTL);
        $acquired = $lock->acquire();
        if (!$acquired) {
            $this->eventBus->dispatch(new TranscodeVideoFail('Skipping task because mutex is already acquired by another worker', $scheduledTask->taskId));
            $this->logger->info('Skipping task because mutex is already acquired by another worker', ['taskId' => $scheduledTask->taskId]);
            return;
        }

        $video = $this->videoRepository->findById($task->videoId());
        if (!$video) {
            $this->eventBus->dispatch(new TranscodeVideoFail('Video not found for transcoding', $task->id()));
            $this->taskRepository->log($task->id(), 'error', 'Video not found for transcoding');
            throw new \RuntimeException('Video not found for transcoding');
        }

        if ($this->cancellationTrigger->isRequested($task->id())) {
            if ($task->canBeCancelled()) {
                $task->cancel();
                $task->updateMeta([
                    'cancelledAt' => new \DateTimeImmutable()->format(DATE_ATOM),
                ]);
                $this->taskRepository->save($task);
                $this->taskRepository->log($task->id(), 'info', 'Task cancelled before ffmpeg start');
            }

            $this->cancellationTrigger->clear($task->id());
            $this->eventBus->dispatch(new TranscodeVideoFail('Task cancelled before ffmpeg start', $task->id()));

            return;
        }

        if (!$task->canStart($video->duration())) {
            $this->eventBus->dispatch(new TranscodeVideoFail('Task cannot be started for transcoding (invalid state or video duration).', $task->id()));
            $this->taskRepository->log($task->id(), 'warning', 'Task cannot be started for transcoding (invalid state or video duration).');
            return;
        }

        try {
            $context = $this->transcodeTaskPreparationService->prepare($task, $video);
            $transcodeReport = $this->transcodeProcessService->run($context);

            if ($transcodeReport->cancelled === true) {
                $this->transcodeTaskFinalizationService->handleCancellation($context->task, $transcodeReport);
                $this->eventBus->dispatch(new TranscodeVideoFail('Transcoding cancelled', $context->task->id()));

                $this->commandBus->dispatch(new StartTaskScheduler());

                return;
            }

            $this->transcodeTaskFinalizationService->handleSuccess($context->task, $context->relativeOutputPath, $transcodeReport);
            $this->eventBus->dispatch(new TranscodeVideoSuccess(
                taskId: $context->task->id(),
                videoId: $context->video->id()?->toRfc4122(),
            ));
        } catch (\Throwable $exception) {
            $this->transcodeTaskFinalizationService->handleFailure($task, $exception);
            $this->eventBus->dispatch(new TranscodeVideoFail(
                error: $exception->getMessage(),
                taskId: $task->id(),
                videoId: $video->id()->toRfc4122(),
            ));
            $this->logger->error('TranscodeVideoHandler failed', [
                'taskId' => $task->id(),
                'videoId' => $video->id()->toRfc4122(),
                'exception' => $exception,
            ]);

            throw $exception;
        } finally {
            try {
                $lock->release();
            } catch (\Throwable $exception) {
                $this->logger->error('Failed to release transcode task mutex', [
                    'taskId' => $scheduledTask->taskId,
                    'exception' => $exception,
                ]);
            }
        }

        $this->commandBus->dispatch(new StartTaskScheduler());
    }
}

