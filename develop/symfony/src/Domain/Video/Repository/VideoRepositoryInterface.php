<?php
declare(strict_types=1);

namespace App\Domain\Video\Repository;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Video;

interface VideoRepositoryInterface extends PaginatedRepositoryInterface
{
    public function save(Video $video): Video;

    public function findById(Uuid $id): ?Video;

    /**
     * Return the 1-based page number (for the given limit) on which the video
     * appears when the full list is ordered by deleted ASC, createdAt DESC.
     */
    public function findVideoPage(Uuid $videoId, Uuid $userId, int $limit): int;

    /**
     * Get active (not deleted) videos count
     * @param Uuid $userId
     * @return int
     */
    public function getActiveCount(Uuid $userId): int;

    /**
     * Get all (include deleted) videos count
     * @param Uuid $userId
     * @return int
     */
    public function getTotalCount(Uuid $userId): int;

    /**
     * @return array<int, Video>
     */
    public function findDeletedVideoForCleanup(): array;
}
