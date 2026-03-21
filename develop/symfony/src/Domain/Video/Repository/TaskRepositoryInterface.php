<?php

namespace App\Domain\Video\Repository;

use App\Domain\Video\Entity\Task;
use Symfony\Component\Uid\UuidV4;

interface TaskRepositoryInterface extends PaginatedRepositoryInterface
{
    public function save(Task $task): void;
    public function findById(int $id): ?Task;
    public function findByIdFresh(int $id): ?Task;
    public function findForTranscode(UuidV4 $videoId, int $presetId, int $userId): ?Task;
    public function log(int $id, string $level, string $text): void;
}
