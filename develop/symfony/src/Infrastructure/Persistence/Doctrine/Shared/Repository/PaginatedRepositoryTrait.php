<?php

namespace App\Infrastructure\Persistence\Doctrine\Shared\Repository;

use App\Domain\Video\DTO\PaginatedResult;

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
            ->orderBy('v.deleted', 'ASC')
            ->addOrderBy('v.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $items = array_map(
            static fn($entity) => self::mapToDomain($entity),
            $entities
        );

        return new PaginatedResult($items, $total);
    }
}
