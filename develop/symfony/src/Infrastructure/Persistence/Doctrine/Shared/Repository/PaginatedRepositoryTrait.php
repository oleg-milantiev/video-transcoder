<?php

namespace App\Infrastructure\Persistence\Doctrine\Shared\Repository;

use App\Application\DTO\PaginatedResult;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Persistence\Doctrine\Video\VideoEntity;
use App\Infrastructure\Persistence\Doctrine\Video\VideoMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;

trait PaginatedRepositoryTrait
{
    public function findAllPaginated(int $page, int $limit): PaginatedResult
    {
        $queryBuilder = $this
            ->createQueryBuilder('v')
            ->select('v');

        $countQuery = clone $queryBuilder;
        $total = (int) $countQuery
            ->select('COUNT(v.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $entities = $queryBuilder
            ->setFirstResult($limit * ($page - 1))
            ->setMaxResults($limit)
            ->orderBy('v.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $videos = array_map(
            static fn($entity)  => self::mapToDomain($entity),
            $entities
        );

        return new PaginatedResult($videos, $total);
    }
}
