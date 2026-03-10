<?php

namespace App\Infrastructure\Persistence\Doctrine\Video;

use App\Application\DTO\PaginatedResult;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VideoEntity>
 */
class VideoRepository extends ServiceEntityRepository implements VideoRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
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
        return VideoMapper::toDomain($this->find($id));
    }

    public function findAllPaginated(int $page, int $limit): PaginatedResult
    {
        $queryBuilder = $this
            ->createQueryBuilder('v')
            ->select('v');

        // Get total count
        $countQuery = clone $queryBuilder;
        $total = (int) $countQuery
            ->select('COUNT(v.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Get paginated results
        $entities = $queryBuilder
            ->setFirstResult($limit * ($page - 1))
            ->setMaxResults($limit)
            ->orderBy('v.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $videos = array_map(
            static fn($entity) => VideoMapper::toDomain($entity),
            $entities
        );

        return new PaginatedResult($videos, $total);
    }
}
