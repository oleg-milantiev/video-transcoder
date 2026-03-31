<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\User;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Entity\Tariff;
use App\Domain\User\Repository\TariffRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

/**
 * @extends ServiceEntityRepository<TariffEntity>
 */
class TariffRepository extends ServiceEntityRepository implements TariffRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TariffEntity::class);
    }

    public function save(Tariff $tariff): void
    {
        $entity = TariffMapper::toDoctrine($tariff);
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?Tariff
    {
        $entity = $this->find(SymfonyUuid::fromString($id->toRfc4122()));
        return $entity ? TariffMapper::toDomain($entity) : null;
    }

    public function findAll(): array
    {
        $entities = parent::findAll();
        return array_map(fn(TariffEntity $entity) => TariffMapper::toDomain($entity), $entities);
    }

    public function delete(Tariff $tariff): void
    {
        $entity = $this->getEntityManager()->getReference(TariffEntity::class, SymfonyUuid::fromString($tariff->id()->toRfc4122()));
        $this->getEntityManager()->remove($entity);
        $this->getEntityManager()->flush();
    }
}
