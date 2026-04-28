<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Video;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\Shared\Repository\PaginatedRepositoryTrait;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

/**
 * @extends ServiceEntityRepository<VideoEntity>
 */
class VideoRepository extends ServiceEntityRepository implements VideoRepositoryInterface
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
                throw new RuntimeException(sprintf('Video with id %s not found', $video->id()));
            }
            VideoMapper::hydrate($entity, $video, $user);
        }

        $em->persist($entity);
        $em->flush();

        return VideoMapper::toDomain($entity);
    }

    /**
     * @throws Exception
     */
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

    public function findById(Uuid $id): ?Video
    {
        $entity = $this->find(SymfonyUuid::fromString($id->toRfc4122()));

        return $entity ? self::mapToDomain($entity) : null;
    }

    /**
     * @throws Exception
     */
    public function findVideoPage(Uuid $videoId, Uuid $userId, int $limit): int
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<< SQL
        WITH ordered_videos AS (
            SELECT
                v.id,
                ROW_NUMBER() OVER (ORDER BY v.deleted ASC, v.created_at DESC) AS row_num
            FROM video v
            WHERE v.user_id = :userId
        )
        SELECT CEIL(ov.row_num / :limit) AS page
        FROM ordered_videos ov
        WHERE ov.id = :videoId
    SQL;

        $page = $conn->executeQuery($sql, [
            'userId' => $userId->toRfc4122(),
            'videoId' => $videoId->toRfc4122(),
            'limit' => $limit
        ])->fetchOne();

        if ($page === false) {
            throw new \InvalidArgumentException('Video not found for this user');
        }

        return (int)$page + 1;
    }

    protected static function mapToDomain(VideoEntity $entity): Video
    {
        return VideoMapper::toDomain($entity);
    }

    /**
     * @throws Exception
     */
    public function getActiveCount(Uuid $userId): int
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<< SQL
            SELECT count(*) AS c
            FROM video v
            WHERE v.user_id = :userId
              AND v.deleted = false
        SQL;

        return (int)$conn->executeQuery($sql, ['userId' => $userId->toRfc4122()])->fetchOne();
    }

    /**
     * @throws Exception
     */
    public function getTotalCount(Uuid $userId): int
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<< SQL
            SELECT count(*) AS c
            FROM video v
            WHERE v.user_id = :userId
        SQL;

        return (int)$conn->executeQuery($sql, ['userId' => $userId->toRfc4122()])->fetchOne();
    }
}
