<?php

namespace App\Domain\Video\Repository;

use App\Domain\Video\DTO\ScheduledTaskDTO;
use App\Domain\Video\Entity\Task;

interface TaskRepositoryInterface extends PaginatedRepositoryInterface
{
    public function save(Task $task): void;
    public function findById(int $id): ?Task;
    public function findByIdFresh(int $id): ?Task;
    public function log(int $id, string $level, string $text): void;
    /**
     * @return ScheduledTaskDTO[]
     */
    public function getScheduled(): array;
}
