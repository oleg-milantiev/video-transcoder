<?php

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Video>
 */
class VideoRepository extends ServiceEntityRepository implements VideoRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Video::class);
    }

    public function save(Video $video, bool $flush = false): void
    {
        $this->getEntityManager()->persist($video);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Video $video, bool $flush = false): void
    {
        $this->getEntityManager()->remove($video);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
