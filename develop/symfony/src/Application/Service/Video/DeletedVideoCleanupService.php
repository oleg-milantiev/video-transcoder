<?php
declare(strict_types=1);

namespace App\Application\Service\Video;

use App\Application\Logging\LogServiceInterface;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use Psr\Log\LogLevel;

final readonly class DeletedVideoCleanupService
{
    public function __construct(
        private VideoRepositoryInterface $videoRepository,
        private StorageInterface $storage,
        private LogServiceInterface $logService,
    ) {
    }

    /**
     * @return array{candidates:int, filesDeleted:int}
     */
    public function cleanup(): array
    {
        $videoCandidates = $this->videoRepository->findDeletedVideoForCleanup();

        $videoFilesDeleted = 0;
        foreach ($videoCandidates as $video) {
            if ($this->cleanupVideo($video)) {
                $videoFilesDeleted++;
            }
        }

        return [
            'candidates' => count($videoCandidates),
            'filesDeleted' => $videoFilesDeleted,
        ];
    }

    public function cleanupVideo(Video $video): bool
    {
        $sourceKey = $video->meta()['sourceKey'] ?? null;
        if (!is_string($sourceKey) || $sourceKey === '') {
            return false;
        }

        $deleted = $this->storage->delete($sourceKey);
        $video->clearSourceKey();
        $this->videoRepository->save($video);

        $this->logService->log(
            'video',
            'delete',
            $video->id(),
            LogLevel::INFO,
            $deleted ? 'Deleted source file for removed video' : 'Source file already missing for removed video',
            [
                'sourceKey' => $sourceKey,
                'deletedNow' => $deleted,
            ],
        );

        return $deleted;
    }
}

