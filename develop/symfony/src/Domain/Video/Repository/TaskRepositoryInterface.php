<?php
declare(strict_types=1);

namespace App\Domain\Video\Repository;

use App\Application\DTO\TaskItemDTO;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Task;
use DateTimeImmutable;

interface TaskRepositoryInterface extends PaginatedRepositoryInterface
{
    public function save(Task $task): void;

    public function findById(Uuid $id): ?Task;

    public function findByIdFresh(Uuid $id): ?Task;

    public function findForTranscode(Uuid $videoId, Uuid $presetId, Uuid $userId, int $height): ?Task;

    /**
     * @return array<int, Task>
     */
    public function findByVideoId(Uuid $videoId): array;

    /**
     * @return array<int, TaskItemDTO>
     */
    public function getDetailsByVideoId(Uuid $videoId): array;

    /**
     * @return array<int, Task>
     */
    public function findDeletedTaskForCleanup(): array;

    /**
     * @return array<int, int> TaskStatus->value => count
     */
    public function getActiveCountByStatus(Uuid $userId): array;

    /**
     * Get active (not deleted) tasks count
     * @param Uuid $userId
     * @return int
     */
    public function getActiveCount(Uuid $userId): int;

    /**
     * Get all (include deleted) tasks count
     * @param Uuid $userId
     * @return int
     */
    public function getTotalCount(Uuid $userId): int;

    /**
     * Get first pending task start time (depend on user tariff)
     * @param Uuid $userId
     * @return DateTimeImmutable|null
     */
    public function getFirstPendingTaskWillStartAt(Uuid $userId): ?DateTimeImmutable;
}
