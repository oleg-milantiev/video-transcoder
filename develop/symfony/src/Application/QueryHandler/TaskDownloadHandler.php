<?php
declare(strict_types=1);

namespace App\Application\QueryHandler;

use App\Application\Exception\TaskDownloadAccessDeniedException;
use App\Application\Exception\TaskNotFoundException;
use App\Application\Exception\VideoNotFoundException;
use App\Application\Logging\LogServiceInterface;
use App\Application\Query\TaskDownloadQuery;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Infrastructure\Security\Voter\VideoAccessVoter;
use Psr\Log\LogLevel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class TaskDownloadHandler
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private VideoRepositoryInterface $videoRepository,
        private LogServiceInterface $logService,
        private Security $security,
        private StorageInterface $storage,
    ) {}

    public function __invoke(TaskDownloadQuery $query): string
    {
        $task = $this->taskRepository->findById($query->taskId);
        if ($task === null) {
            throw new TaskNotFoundException('Task not found');
        }

        $video = $this->videoRepository->findById($task->videoId());
        if ($video === null) {
            throw new VideoNotFoundException('Video not found');
        }

        if (!$this->security->isGranted(VideoAccessVoter::CAN_DOWNLOAD_TRANSCODE, $video)) {
            throw new TaskDownloadAccessDeniedException('Access denied');
        }

        if ($task->status() !== TaskStatus::COMPLETED) {
            throw new TaskNotFoundException('Task output is not ready');
        }

        if ($task->isDeleted()) {
            throw new TaskNotFoundException('Task is deleted');
        }

        $output = $task->meta()['output'] ?? null;
        if (!$output) {
            throw new TaskNotFoundException('Output file not found');
        }

        $this->logService->log('task', 'download', $task->id(), LogLevel::INFO, 'Transcode result downloaded', [
            'videoId' => $video->id()?->toRfc4122(),
            'output' => $output,
            'userId' => $query->requestedByUserId->toRfc4122(),
        ]);

        return $this->storage->publicUrl($output);
    }
}
