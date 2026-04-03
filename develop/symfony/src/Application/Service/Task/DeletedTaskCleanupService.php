<?php
declare(strict_types=1);

namespace App\Application\Service\Task;

use App\Application\Logging\LogServiceInterface;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use Psr\Log\LogLevel;

final readonly class DeletedTaskCleanupService
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private StorageInterface $storage,
        private LogServiceInterface $logService,
    ) {
    }

    /**
     * @return array{candidates:int, filesDeleted:int}
     */
    public function cleanup(): array
    {
        $taskCandidates = $this->taskRepository->findDeletedTaskForCleanup();

        $taskFilesDeleted = 0;
        foreach ($taskCandidates as $task) {
            if ($this->cleanupTask($task)) {
                $taskFilesDeleted++;
            }
        }

        return [
            'candidates' => count($taskCandidates),
            'filesDeleted' => $taskFilesDeleted,
        ];
    }

    /**
     * @return array{candidates:int, filesDeleted:int}
     */
    public function cleanupByVideoId(Uuid $videoId): array
    {
        $tasks = $this->taskRepository->findByVideoId($videoId);

        $candidates = 0;
        $filesDeleted = 0;
        foreach ($tasks as $task) {
            if (!$task->isDeleted()) {
                continue;
            }

            $candidates++;
            if ($this->cleanupTask($task)) {
                $filesDeleted++;
            }
        }

        return [
            'candidates' => $candidates,
            'filesDeleted' => $filesDeleted,
        ];
    }

    public function cleanupTask(Task $task): bool
    {
        $outputKey = $task->meta()['output'] ?? null;
        if (!is_string($outputKey) || $outputKey === '') {
            return false;
        }

        $deleted = $this->storage->delete($outputKey);
        $task->clearOutput();
        $this->taskRepository->save($task);

        $this->logService->log(
            'task',
            'delete',
            $task->id(),
            LogLevel::INFO,
            $deleted ? 'Deleted transcoded output for removed task' : 'Transcoded output already missing for removed task',
            [
                'outputKey' => $outputKey,
                'deletedNow' => $deleted,
                'videoId' => $task->videoId()?->toRfc4122(),
            ],
        );

        return $deleted;
    }
}
