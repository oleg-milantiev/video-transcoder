<?php

namespace App\Infrastructure\Persistence\Doctrine\Video;

use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

    public function save(Video $video): void
    {
        $this->getEntityManager()->persist(VideoMapper::toDoctrine($video));
        $this->getEntityManager()->flush();
    }

    public function findById(int $id): ?Video
    {
        return VideoMapper::toDomain($this->find($id));
    }
}
