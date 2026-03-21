<?php

namespace App\Infrastructure\Persistence\Doctrine\Video;

use App\Application\Query\Repository\VideoDetailsReadRepositoryInterface;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\Shared\Repository\PaginatedRepositoryTrait;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;

/**
 * @extends ServiceEntityRepository<VideoEntity>
 */
class VideoRepository extends ServiceEntityRepository implements VideoRepositoryInterface, VideoDetailsReadRepositoryInterface
{
    use PaginatedRepositoryTrait;

    public function __construct(
        ManagerRegistry $registry,
        private readonly LoggerInterface $logger,
    )
    {
        parent::__construct($registry, VideoEntity::class);
    }

    /**
     * @throws ORMException
     */
    public function save(Video $video): Video
    {
        $em = $this->getEntityManager();
        $user = $em->getReference(UserEntity::class, $video->userId());

        if ($video->id() === null) {
            $video->generateId();
            $entity = VideoMapper::toDoctrine($video, $user);
        } else {
            $entity = $this->find($video->id());
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
        return self::mapToDomain($this->find($id));
    }

    protected static function mapToDomain(VideoEntity $entity): Video
    {
        return VideoMapper::toDomain($entity);
    }

    /**
     * @throws Exception
     */
    public function getDetailsByVideoId(UuidV4 $videoId): array
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
                TO_CHAR(t.created_at, 'YYYY-MM-DD HH24:MI') as created_at
            FROM preset p
            LEFT JOIN task t ON p.id = t.preset_id AND t.video_id = :videoId
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
                ] : null,
            ];
        }

        return $presetsWithTasks;
    }

    // You should NOT log into Persistence in prod. Just for debug now
    public function log(Uuid $id, string $level, string $text, array $context = []): void
    {
        $this->logger->log($level, $text, $context);

        $em = $this->getEntityManager();
        /** @var VideoEntity|null $video */
        $video = $this->find($id);
        if (!$video) {
            throw new \RuntimeException("Video with id $id not found");
        }
        $log = $video->log ?? [];
        $log[] = [
            'level' => $level,
            'text' => $text,
            'timestamp' => new \DateTimeImmutable()->format(DATE_ATOM),
        ];
        $video->log = $log;
        $video->updatedAt = new \DateTimeImmutable();
        $em->persist($video);
        $em->flush();
    }
}
