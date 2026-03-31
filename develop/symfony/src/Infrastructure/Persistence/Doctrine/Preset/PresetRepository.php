<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Preset;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

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

    public function findById(Uuid $id): ?Preset
    {
        $entity = $this->find(SymfonyUuid::fromString($id->toRfc4122()));

        return $entity ? PresetMapper::toDomain($entity) : null;
    }

    // You should NOT log into Persistence in prod. Just for debug now
    public function log(Uuid $id, string $level, string $text): void
    {
        $em = $this->getEntityManager();
        /** @var PresetEntity|null $preset */
        $preset = $this->find(SymfonyUuid::fromString($id->toRfc4122()));
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
