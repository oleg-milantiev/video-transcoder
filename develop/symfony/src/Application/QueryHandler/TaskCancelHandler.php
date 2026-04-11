<?php
declare(strict_types=1);

namespace App\Application\QueryHandler;

use App\Application\Exception\TaskCancelAccessDeniedException;
use App\Application\Exception\TaskNotFoundException;
use App\Application\Exception\VideoNotFoundException;
use App\Application\Logging\LogServiceInterface;
use App\Application\Query\TaskCancelQuery;
use App\Application\Service\Storage\StorageRealtimeNotifier;
use App\Application\Service\Task\TaskRealtimeNotifier;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\ValueObject\TaskStatus;
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
        private StorageRealtimeNotifier $storageNotifier,
    ) {}

    public function __invoke(TaskCancelQuery $query): array
    {
        $task = $this->taskRepository->findById($query->taskId);
        if ($task === null) {
            throw new TaskNotFoundException('Task not found');
        }

        $video = $this->videoRepository->findById($task->videoId());
        if ($video === null) {
            throw new VideoNotFoundException('Video not found');
        }

        if (!$this->security->isGranted(VideoAccessVoter::CAN_CANCEL_TRANSCODE, $video)) {
            throw new TaskCancelAccessDeniedException('Access denied');
        }

        $task->updateMeta([
            'cancelledByUserId' => $query->requestedByUserId->toRfc4122(),
            'cancelRequestedAt' => new \DateTimeImmutable()->format(\DateTimeInterface::ATOM),
        ]);

        $cancelledNow = $task->status() === TaskStatus::PENDING || $task->status() === TaskStatus::STARTING;

        if ($cancelledNow) {
            $task->cancel();
            $task->clearSizeExpected();
            $this->logService->log('task', 'cancel', $task->id(), LogLevel::INFO, 'Task cancelled before start', [
                'videoId' => $video->id()?->toRfc4122(),
                'userId' => $query->requestedByUserId?->toRfc4122(),
            ]);
        } else {
            $this->logService->log('task', 'cancel', $task->id(), LogLevel::INFO, 'Cancellation requested in progress', [
                'videoId' => $video->id()?->toRfc4122(),
                'userId' => $query->requestedByUserId?->toRfc4122(),
            ]);
        }

        $this->taskRepository->save($task);

        $this->taskRealtimeNotifier->notifyTaskUpdated($task, $cancelledNow ? 'cancelled' : 'cancel_requested');
        $this->cancellationTrigger->request($task->id());

        if ($cancelledNow) {
            $this->storageNotifier->notifyStorageUpdated($task->userId());
        }

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
