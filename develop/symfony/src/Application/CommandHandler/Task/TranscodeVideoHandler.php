<?php
declare(strict_types=1);

namespace App\Application\CommandHandler\Task;

use App\Application\Command\Task\StartTaskScheduler;
use App\Application\Command\Task\TranscodeVideo;
use App\Application\Event\TranscodeVideoFail;
use App\Application\Event\TranscodeVideoStart;
use App\Application\Event\TranscodeVideoSuccess;
use App\Application\Exception\StorageSizeExceedsQuota;
use App\Infrastructure\Persistence\Doctrine\User\UserRepository;
use Psr\Log\LogLevel;
use App\Application\Logging\LogServiceInterface;
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
        private LogServiceInterface $logService,
        private LoggerInterface $logger,
        private LockFactory $lockFactory,
        private TaskCancellationTrigger $cancellationTrigger,
        private TranscodeProcessService $transcodeProcessService,
        private TranscodeTaskPreparationService $transcodeTaskPreparationService,
        private TranscodeTaskFinalizationService $transcodeTaskFinalizationService,
        private UserRepository $userRepository,
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function __invoke(TranscodeVideo $command): void
    {
        // todo зачем эта матрёшка?
        $scheduledTask = $command->scheduledTask;
        $this->eventBus->dispatch(new TranscodeVideoStart(
            taskId: $scheduledTask->taskId->toRfc4122(),
            userId: $scheduledTask->userId->toRfc4122(),
            videoId: $scheduledTask->videoId->toRfc4122(),
        ));

        $task = $this->taskRepository->findByIdFresh($scheduledTask->taskId);

        if (!$task) {
            $this->eventBus->dispatch(new TranscodeVideoFail('Scheduled task not found for transcoding', $scheduledTask->taskId->toRfc4122()));
            $this->logger->error('Scheduled task not found for transcoding', ['taskId' => $scheduledTask->taskId->toRfc4122()]);
            return;
        }

        $lock = $this->lockFactory->createLock(sprintf('transcode-task:%s', $scheduledTask->taskId->toRfc4122()), self::TASK_MUTEX_TTL);
        $acquired = $lock->acquire();
        if (!$acquired) {
            $this->eventBus->dispatch(new TranscodeVideoFail('Skipping task because mutex is already acquired by another worker', $scheduledTask->taskId->toRfc4122()));
            $this->logger->info('Skipping task because mutex is already acquired by another worker', ['taskId' => $scheduledTask->taskId->toRfc4122()]);
            return;
        }

        $video = $this->videoRepository->findById($task->videoId());
        if (!$video) {
            $this->eventBus->dispatch(new TranscodeVideoFail('Video not found for transcoding', $task->id()->toRfc4122()));
            $this->logService->log('task', $task->id(), LogLevel::ERROR, 'Video not found for transcoding');
            throw new \RuntimeException('Video not found for transcoding');
        }

        if ($this->cancellationTrigger->isRequested($task->id())) {
            if ($task->canBeCancelled()) {
                $task->cancel();
                $task->updateMeta([
                    'cancelledAt' => new \DateTimeImmutable()->format(DATE_ATOM),
                ]);
                $this->taskRepository->save($task);
                $this->logService->log('task', $task->id(), LogLevel::INFO, 'Task cancelled before ffmpeg start');
            }

            $this->cancellationTrigger->clear($task->id());
            $this->eventBus->dispatch(new TranscodeVideoFail('Task cancelled before ffmpeg start', $task->id()->toRfc4122()));

            return;
        }

        if (!$task->canStart($video->duration())) {
            $this->eventBus->dispatch(new TranscodeVideoFail('Task cannot be started for transcoding (invalid state or video duration).', $task->id()->toRfc4122()));
            $this->logService->log('task', $task->id(), LogLevel::WARNING, 'Task cannot be started for transcoding (invalid state or video duration).', [
                'duration' => $video->duration(),
                'status' => $task->status()->name,
                'startedAt' => $task->startedAt()?->format(DATE_ATOM),
            ]);
            return;
        }

        // tariff checks (storage)
        // todo ckeck it
        $user = $this->userRepository->findById($scheduledTask->userId);
        $tariff = $user->tariff();

        $fileSizeMb = $video->size();
        $storageNowMb = ($this->videoRepository->getStorageSize($user->id()) + $this->taskRepository->getStorageSize($user->id()))/1024/1024;
        $storageCapacityMb = $tariff->storageGb()->value()*1024;
        if ($fileSizeMb + $storageNowMb > $storageCapacityMb) {
            throw StorageSizeExceedsQuota::create($fileSizeMb, $storageNowMb, $storageCapacityMb);
        }

        // all good. Start transcode
        $context = $this->transcodeTaskPreparationService->prepare($task, $video);
        try {
            $transcodeReport = $this->transcodeProcessService->run($context);

            if ($transcodeReport->cancelled === true) {
                $this->transcodeTaskFinalizationService->handleCancellation($context->task, $transcodeReport);
                $this->eventBus->dispatch(new TranscodeVideoFail('Transcoding cancelled', $context->task->id()->toRfc4122()));

                $this->commandBus->dispatch(new StartTaskScheduler());

                return;
            }

            $this->transcodeTaskFinalizationService->handleSuccess($context->task, $context, $transcodeReport);
            $this->eventBus->dispatch(new TranscodeVideoSuccess(
                taskId: $context->task->id()->toRfc4122(),
                videoId: $context->video->id()?->toRfc4122(),
            ));
        } catch (\Throwable $exception) {
            $this->transcodeTaskFinalizationService->handleFailure($task, $exception, $context);
            $this->eventBus->dispatch(new TranscodeVideoFail(
                error: $exception->getMessage(),
                taskId: $task->id()->toRfc4122(),
                videoId: $video->id()->toRfc4122(),
            ));
            // TODO масло масляное. Выше TranscodeVideoFail уже логирует
            $this->logger->error('TranscodeVideoHandler failed', [
                'taskId' => $task->id()->toRfc4122(),
                'videoId' => $video->id()->toRfc4122(),
                'exception' => $exception,
            ]);

            throw $exception;
        } finally {
            try {
                $lock->release();
            } catch (\Throwable $exception) {
                $this->logger->error('Failed to release transcode task mutex', [
                    'taskId' => $scheduledTask->taskId->toRfc4122(),
                    'exception' => $exception,
                ]);
            }
        }

        $this->commandBus->dispatch(new StartTaskScheduler());
    }
}
