<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Storage;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Repository\StorageRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

class StorageRepository implements StorageRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function getUsedStorageSize(Uuid $userId): int
    {
        $conn = $this->em->getConnection();

        $sql = <<< SQL
            WITH active_user_videos_size AS (
                SELECT sum((v.meta ->> 'size')::bigint) AS size
                FROM video v
                WHERE v.user_id = :userId
                  AND v.deleted = false
            ),
            active_user_tasks_size AS (
                SELECT sum((COALESCE(t.meta ->> 'size', t.meta ->> 'sizeExpected'))::bigint) AS size
                FROM task t
                WHERE t.user_id = :userId
                  AND t.deleted = false
            )
            SELECT COALESCE(active_user_tasks_size.size, 0) + COALESCE(active_user_videos_size.size, 0) AS size
            FROM active_user_videos_size, active_user_tasks_size;
        SQL;

        return (int) $conn->executeQuery($sql, ['userId' => $userId->toRfc4122()])->fetchOne();
    }

    public function deleteExpiredVideosAndTasks(): int
    {
        $conn = $this->em->getConnection();

        $sql = <<<SQL
            WITH deleted_videos_list AS (
                SELECT v.id, v.deleted
                FROM video v
                         JOIN "user" u ON v.user_id = u.id
                         JOIN tariff t ON u.tariff_id = t.id
                WHERE t.storage_hour = 0
                   OR v.created_at <= NOW() - (t.storage_hour || ' hours')::interval
            ),
                 update_videos AS (
                     UPDATE video
                         SET deleted = true
                         WHERE id IN (SELECT id FROM deleted_videos_list)
                 ),
                 update_tasks AS (
                     UPDATE task
                         SET deleted = true
                         WHERE video_id IN (SELECT id FROM deleted_videos_list)
                 )
            SELECT count(*) as updated_videos_count
            FROM deleted_videos_list
            WHERE deleted = false
        SQL;

        $result = $conn->executeQuery($sql);
        return $result->fetchOne();
    }

    public function getStorageMetrics(Uuid $userId): array
    {
        // todo
        return [];
    }
}
