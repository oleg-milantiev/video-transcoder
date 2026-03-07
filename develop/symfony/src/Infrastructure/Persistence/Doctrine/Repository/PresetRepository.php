<?php

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Preset>
 */
class PresetRepository extends ServiceEntityRepository implements PresetRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Preset::class);
    }

    public function save(Preset $preset): void
    {
        $this->getEntityManager()->persist($preset);
        $this->getEntityManager()->flush();
    }

    public function findById(int $id): ?Preset
    {
        return $this->find($id);
    }
}
