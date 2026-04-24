<?php
declare(strict_types=1);

namespace App\Domain\Video\Repository;

use App\Domain\Shared\ValueObject\Uuid;

interface StorageRepositoryInterface
{
    /**
     * Get active videos (and tasks) sizes sum
     * @return int Size in bytes
     */
    public function getUsedStorageSize(Uuid $userId): int;

    /**
     * Get will delete it in 24h videos (and tasks) size
     * @return int Size in bytes
     */
    public function getDeletedIn24hSize(Uuid $userId): int;

    /**
     * Mark videos (and tasks) as deleted that are older than User.Tariff.storageHour
     * @return int Number of videos deleted
     */
    public function deleteExpiredVideosAndTasks(): int;
}
