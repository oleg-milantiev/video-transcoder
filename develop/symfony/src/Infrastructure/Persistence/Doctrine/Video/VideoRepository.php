<?php

namespace App\Infrastructure\Persistence\Doctrine\Video;

use App\Application\Query\Repository\VideoDetailsReadRepositoryInterface;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\Shared\Repository\PaginatedRepositoryTrait;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

/**
 * @extends ServiceEntityRepository<VideoEntity>
 */
class VideoRepository extends ServiceEntityRepository implements VideoRepositoryInterface, VideoDetailsReadRepositoryInterface
{
    use PaginatedRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoEntity::class);
    }

    /**
     * @throws ORMException
     */
    public function save(Video $video): Video
    {
        $em = $this->getEntityManager();
        $user = $em->getReference(UserEntity::class, SymfonyUuid::fromString($video->userId()->toRfc4122()));

        if ($video->id() === null) {
            $entity = VideoMapper::toDoctrine($video, $user);
        } else {
            $entity = $this->find(SymfonyUuid::fromString($video->id()->toRfc4122()));
            if (!$entity) {
                throw new \RuntimeException(sprintf('Video with id %s not found', $video->id()));
            }
            VideoMapper::hydrate($entity, $video, $user);
        }

        $em->persist($entity);
        $em->flush();

        return VideoMapper::toDomain($entity);
    }

    public function findById(Uuid $id): ?Video
    {
        $entity = $this->find(SymfonyUuid::fromString($id->toRfc4122()));

        return $entity ? self::mapToDomain($entity) : null;
    }

    public function getStorageSize(Uuid $userId): int
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "SELECT sum((v.meta->>'size')::bigint)
            FROM video v
            WHERE v.user_id = :userId
                AND v.deleted = false";

        $size = $conn->executeQuery($sql, ['userId' => $userId->toRfc4122()])->fetchOne();

        return $size ? (int)$size : 0;
    }

    public function findDeletedVideoForCleanup(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            SELECT id
            FROM video
            WHERE deleted = true
              AND COALESCE(meta::jsonb ->> 'sourceKey', '') <> ''
            ORDER BY updated_at NULLS FIRST, created_at
        SQL;

        $rows = $conn->executeQuery($sql)->fetchAllAssociative();

        return array_values(array_filter(array_map(function (array $row): ?Video {
            return $this->findById(Uuid::fromString($row['id']));
        }, $rows)));
    }

    protected static function mapToDomain(VideoEntity $entity): Video
    {
        return VideoMapper::toDomain($entity);
    }

    /**
     * @throws Exception
     */
    public function getDetailsByVideoId(Uuid $videoId): array
    {
        $conn = $this->getEntityManager()->getConnection();

        // SQL query to fetch all presets with their tasks for this video, sorted by preset title
        $sql = <<<SQL
            SELECT
                p.id,
                p.title,
                t.id AS task_id,
                t.status,
                t.progress,
                CONCAT(v.title, ' - ', p.title) as download_filename,
                TO_CHAR(t.created_at, 'YYYY-MM-DD HH24:MI') as created_at
            FROM preset p
            LEFT JOIN task t ON p.id = t.preset_id AND t.video_id = :videoId
            LEFT JOIN video v ON t.video_id = v.id
            ORDER BY p.title
        SQL;

        $result = $conn->executeQuery($sql, ['videoId' => $videoId->toRfc4122()]);
        $rows = $result->fetchAllAssociative();

        $presetsWithTasks = [];
        foreach ($rows as $row) {
            $presetsWithTasks[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'task' => $row['task_id'] !== null ? [
                    'id' => $row['task_id'],
                    'status' => (int)$row['status'],
                    'progress' => (int)$row['progress'],
                    'createdAt' => $row['created_at'],
                    'downloadFilename' => $row['download_filename'],
                ] : null,
            ];
        }

        return $presetsWithTasks;
    }

    public function deleteExpiredVideosAndTasks(): int
    {
        $conn = $this->getEntityManager()->getConnection();

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
}
