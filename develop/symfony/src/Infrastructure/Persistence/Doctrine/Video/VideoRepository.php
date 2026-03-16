<?php

namespace App\Infrastructure\Persistence\Doctrine\Video;

use App\Application\DTO\PaginatedResult;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\Shared\Repository\PaginatedRepositoryTrait;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<VideoEntity>
 */
class VideoRepository extends ServiceEntityRepository implements VideoRepositoryInterface
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
    public function save(Video $video): void
    {
        $em = $this->getEntityManager();

        $em->persist(VideoMapper::toDoctrine($video, $em->getReference(UserEntity::class, $video->userId())));
        $em->flush();
    }

    public function findById(int $id): ?Video
    {
        return self::mapToDomain($this->find($id));
    }

    protected static function mapToDomain(VideoEntity $entity): Video
    {
        return VideoMapper::toDomain($entity);
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
