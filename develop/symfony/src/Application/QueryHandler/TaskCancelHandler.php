<?php

namespace App\Application\QueryHandler;

use App\Application\Exception\TaskNotFoundException;
use App\Application\Exception\TranscodeAccessDeniedException;
use App\Application\Exception\VideoNotFoundException;
use App\Application\Logging\LogServiceInterface;
use App\Application\Query\TaskCancelQuery;
use App\Application\Service\Task\TaskRealtimeNotifier;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Infrastructure\Security\Voter\VideoAccessVoter;
use App\Infrastructure\Task\TaskCancellationTrigger;
use Psr\Log\LogLevel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class TaskCancelHandler
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private VideoRepositoryInterface $videoRepository,
        private LogServiceInterface $logService,
        private TaskCancellationTrigger $cancellationTrigger,
        private TaskRealtimeNotifier $taskRealtimeNotifier,
        private Security $security,
    ) {}

    public function __invoke(TaskCancelQuery $query): array
    {
        $task = $this->taskRepository->findById($query->taskId);
        if ($task === null) {
            throw new TaskNotFoundException('Task not found');
        }

        $video = $this->videoRepository->findById($task->videoId());
        if ($video === null) {
            throw new VideoNotFoundException('Video not found.');
        }

        if (!$this->security->isGranted(VideoAccessVoter::CAN_CANCEL_TRANSCODE, $video)) {
            throw new TranscodeAccessDeniedException('Access denied');
        }

        $task->updateMeta([
            'cancelledByUserId' => $query->requestedByUserId->toRfc4122(),
            'cancelRequestedAt' => new \DateTimeImmutable()->format(DATE_ATOM),
        ]);

        $cancelledNow = $task->status() === TaskStatus::PENDING || $task->status() === TaskStatus::STARTING;

        if ($cancelledNow) {
            $task->cancel();
            $this->logService->log('task', $task->id(), LogLevel::INFO, 'Task cancelled before start', [
                'videoId' => $video->id()?->toRfc4122(),
                'requestedByUserId' => $query->requestedByUserId?->toRfc4122(),
            ]);
        } else {
            $this->logService->log('task', $task->id(), LogLevel::INFO, 'Cancellation requested in progress', [
                'videoId' => $video->id()?->toRfc4122(),
                'requestedByUserId' => $query->requestedByUserId?->toRfc4122(),
            ]);
        }

        $this->logService->log('video', $video->id(), LogLevel::INFO, 'Cancellation requested for video task', [
            'taskId' => $task->id()->toRfc4122(),
            'requestedByUserId' => $query->requestedByUserId?->toRfc4122(),
            'cancelledNow' => $cancelledNow,
        ]);

        $this->logService->log('user', $query->requestedByUserId, LogLevel::INFO, 'User requested transcode cancellation', [
            'taskId' => $task->id()->toRfc4122(),
            'videoId' => $video->id()?->toRfc4122(),
            'cancelledNow' => $cancelledNow,
        ]);

        $this->taskRepository->save($task);

        $this->taskRealtimeNotifier->notifyTaskUpdated($task, $cancelledNow ? 'cancelled' : 'cancel_requested');
        $this->cancellationTrigger->request($task->id());

        // todo DTO
        return [
            'task' => [
                'id' => $task->id()->toRfc4122(),
                'status' => $task->status()->name,
                'cancelledNow' => $cancelledNow,
                'cancellationRequested' => true,
            ],
        ];
    }
}
