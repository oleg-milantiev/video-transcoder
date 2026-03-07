<?php

namespace App\Infrastructure\Persistence\Doctrine\Preset;

use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PresetEntity>
 */
class PresetRepository extends ServiceEntityRepository implements PresetRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PresetEntity::class);
    }

    public function save(Preset $preset): void
    {
        $this->getEntityManager()->persist(PresetMapper::toDoctrine($preset));
        $this->getEntityManager()->flush();
    }

    public function findById(int $id): ?Preset
    {
        return PresetMapper::toDomain($this->find($id));
    }
}
