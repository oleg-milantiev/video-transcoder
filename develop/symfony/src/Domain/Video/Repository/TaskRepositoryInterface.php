<?php

namespace App\Domain\Video\Repository;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Task;

interface TaskRepositoryInterface extends PaginatedRepositoryInterface
{
    public function save(Task $task): void;
    public function findById(Uuid $id): ?Task;
    public function findByIdFresh(Uuid $id): ?Task;
    public function findForTranscode(Uuid $videoId, Uuid $presetId, Uuid $userId): ?Task;

    public function getStorageSize(Uuid $userId): int;

    /**
     * @return array<int, Task>
     */
    public function findByVideoId(Uuid $videoId): array;

    /**
     * @return array<int, Task>
     */
    public function findDeletedTaskForCleanup(): array;
}
