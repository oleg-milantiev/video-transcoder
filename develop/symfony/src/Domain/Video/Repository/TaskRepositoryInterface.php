<?php

namespace App\Domain\Video\Repository;

use App\Domain\Video\Entity\Task;
use Symfony\Component\Uid\UuidV4;

interface TaskRepositoryInterface extends PaginatedRepositoryInterface
{
    public function save(Task $task): void;
    public function findById(UuidV4 $id): ?Task;
    public function findByIdFresh(UuidV4 $id): ?Task;
    public function findForTranscode(UuidV4 $videoId, UuidV4 $presetId, UuidV4 $userId): ?Task;

    /**
     * @return array<int, Task>
     */
    public function findByVideoId(UuidV4 $videoId): array;

    /**
     * @return array<int, Task>
     */
    public function findDeletedTaskForCleanup(): array;
}
