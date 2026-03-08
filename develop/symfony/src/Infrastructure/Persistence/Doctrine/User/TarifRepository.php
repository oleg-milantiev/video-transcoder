<?php

namespace App\Infrastructure\Persistence\Doctrine\User;

use App\Domain\User\Entity\Tarif;
use App\Domain\User\Repository\TarifRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TarifEntity>
 */
class TarifRepository extends ServiceEntityRepository implements TarifRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TarifEntity::class);
    }

    public function save(Tarif $tarif): void
    {
        $entity = TarifMapper::toDoctrine($tarif);
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }

    public function findById(int $id): ?Tarif
    {
        $entity = $this->find($id);
        return $entity ? TarifMapper::toDomain($entity) : null;
    }

    public function findAll(): array
    {
        $entities = parent::findAll();
        return array_map(fn(TarifEntity $entity) => TarifMapper::toDomain($entity), $entities);
    }

    public function delete(Tarif $tarif): void
    {
        $entity = $this->getEntityManager()->getReference(TarifEntity::class, $tarif->id());
        $this->getEntityManager()->remove($entity);
        $this->getEntityManager()->flush();
    }
}
