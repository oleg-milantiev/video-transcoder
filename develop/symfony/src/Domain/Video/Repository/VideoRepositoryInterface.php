<?php

namespace App\Domain\Video\Repository;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Video;

interface VideoRepositoryInterface extends PaginatedRepositoryInterface
{
    public function save(Video $video): Video;
    public function findById(Uuid $id): ?Video;

    public function getStorageSize(Uuid $userId): int;

    /**
     * @return array<int, Video>
     */
    public function findDeletedVideoForCleanup(): array;

    /**
     * Mark videos (and tasks) as deleted that are older than User.Tariff.storageHour
     * @return int Number of videos deleted
     */
    public function deleteExpiredVideosAndTasks(): int;
}
