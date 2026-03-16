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

    // You should NOT log into Persistence in prod. Just for debug now
    public function log(int $id, string $level, string $text): void
    {
        $em = $this->getEntityManager();
        /** @var PresetEntity|null $preset */
        $preset = $this->find($id);
        if (!$preset) {
            throw new \RuntimeException("Preset with id $id not found");
        }
        $log = $preset->log ?? [];
        $log[] = [
            'level' => $level,
            'text' => $text,
            'timestamp' => new \DateTimeImmutable()->format(DATE_ATOM),
        ];
        $preset->log = $log;
        $em->persist($preset);
        $em->flush();
    }
}
