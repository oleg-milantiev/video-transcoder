<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Preset;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Entity\Tariff;
use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
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

    public function findByTariff(Tariff $tariff): array
    {
        $qb = $this->createQueryBuilder('p');
        $qb->join('p.tariffs', 't')
            ->where('t.id = :tariffId')
            ->setParameter('tariffId', SymfonyUuid::fromString($tariff->id()->toRfc4122()));

        return array_map(
            static fn(PresetEntity $entity) => PresetMapper::toDomain($entity),
            $qb->getQuery()->getResult(),
        );
    }

    public function findById(Uuid $id): ?Preset
    {
        $entity = $this->find(SymfonyUuid::fromString($id->toRfc4122()));

        return $entity ? PresetMapper::toDomain($entity) : null;
    }

    /**
     * @throws Exception
     */
    public function findForUser(Uuid $userId): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<SQL
            SELECT p.id
            FROM preset p
            INNER JOIN tariff_preset tp ON tp.preset_entity_id = p.id
            INNER JOIN "user" u ON u.tariff_id = tp.tariff_entity_id
            WHERE u.id = :userId
            ORDER BY p.title
        SQL;

        $rows = $conn->executeQuery($sql, ['userId' => $userId->toRfc4122()])->fetchAllAssociative();

        return array_values(array_filter(array_map(
            fn(array $row): ?Preset => $this->findById(Uuid::fromString((string)$row['id'])),
            $rows,
        )));
    }
}
