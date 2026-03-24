<?php

declare(strict_types=1);

// TODO не нравится место и кучность. Разделить и таски к таскам, видео к видео
namespace App\Application\Service\Maintenance;

use App\Application\Logging\LogServiceInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use Psr\Log\LogLevel;

final readonly class DeletedMediaCleanupService
{
    public function __construct(
        private VideoRepositoryInterface $videoRepository,
        private TaskRepositoryInterface $taskRepository,
        private StorageInterface $storage,
        private LogServiceInterface $logService,
    ) {
    }

    /**
     * @return array{videoCandidates:int, taskCandidates:int, videoFilesDeleted:int, taskFilesDeleted:int}
     */
    // TODO не использовал пока. Проверить после переделки storageInterface
    public function cleanup(int $limit = 100): array
    {
        $videoCandidates = $this->videoRepository->findDeletedVideoForCleanup($limit);

        $videoFilesDeleted = 0;
        foreach ($videoCandidates as $candidate) {
            $sourcePath = $candidate['sourcePath'] ?? null;
            if (!is_string($sourcePath) || $sourcePath === '') {
                continue;
            }

            $deleted = $this->storage->delete($sourcePath);
            if ($deleted) {
                $videoFilesDeleted++;
            }

            $this->logService->log(
                'video',
                $candidate['videoId'],
                LogLevel::INFO,
                $deleted ? 'Deleted source file for removed video' : 'Source file already missing for removed video',
                [
                    'path' => $sourcePath,
                    'deletedNow' => $deleted,
                ],
            );
        }

        $taskCandidates = $this->taskRepository->findDeletedTaskForCleanup($limit);
        $taskFilesDeleted = 0;
        foreach ($taskCandidates as $candidate) {
            $outputPath = $candidate['outputPath'] ?? null;
            if (!is_string($outputPath) || $outputPath === '') {
                continue;
            }

            $deleted = $this->storage->delete($outputPath);
            if ($deleted) {
                $taskFilesDeleted++;
            }

            $this->logService->log(
                'task',
                $candidate['taskId'],
                LogLevel::INFO,
                $deleted ? 'Deleted transcoded output for removed task' : 'Transcoded output already missing for removed task',
                [
                    'path' => $outputPath,
                    'deletedNow' => $deleted,
                    'videoId' => $candidate['videoId']->toRfc4122(),
                ],
            );
        }

        return [
            'videoCandidates' => count($videoCandidates),
            'taskCandidates' => count($taskCandidates),
            'videoFilesDeleted' => $videoFilesDeleted,
            'taskFilesDeleted' => $taskFilesDeleted,
        ];
    }
}
