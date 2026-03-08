<?php

namespace App\Domain\Video\Repository;

use App\Domain\Video\Entity\Task;

interface TaskRepositoryInterface
{
    public function save(Task $task): void;
    public function findById(int $id): ?Task;
}
