<?php
declare(strict_types=1);

namespace App\Application\QueryHandler;

use App\Application\Exception\TaskNotFoundException;
use App\Application\Exception\TranscodeAccessDeniedException;
use App\Application\Logging\LogServiceInterface;
use App\Application\Query\DeleteTaskQuery;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Infrastructure\Security\Voter\VideoAccessVoter;
use Psr\Log\LogLevel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class DeleteTaskHandler
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private VideoRepositoryInterface $videoRepository,
        private LogServiceInterface $logService,
        private Security $security,
    ) {
    }

    public function __invoke(DeleteTaskQuery $query): void
    {
        $task = $this->taskRepository->findById($query->taskId);
        if ($task === null) {
            throw new TaskNotFoundException('Task not found');
        }

        $video = $this->videoRepository->findById($task->videoId());
        if ($video === null) {
            throw new \DomainException('Task video not found');
        }

        if (!$this->security->isGranted(VideoAccessVoter::CAN_DELETE, $video)) {
            throw new TranscodeAccessDeniedException('Access denied');
        }

        if ($task->status()->isTranscoding()) {
            throw new \DomainException('Task is active and cannot be deleted.');
        }

        $task->markDeleted();
        $this->taskRepository->save($task);

        $context = [
            'taskId' => $task->id()?->toRfc4122(),
            'videoId' => $task->videoId()->toRfc4122(),
            'requestedByUserId' => $query->requestedByUserId->toRfc4122(),
        ];

        $this->logService->log('task', 'delete', $task->id(), LogLevel::INFO, 'Task marked as deleted', $context);
    }
}
