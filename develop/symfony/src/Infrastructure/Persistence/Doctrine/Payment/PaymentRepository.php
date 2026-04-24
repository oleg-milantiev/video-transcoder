<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Payment;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Entity\Payment;
use App\Domain\User\Repository\PaymentRepositoryInterface;
use App\Domain\User\ValueObject\PaymentExternalId;
use App\Domain\User\ValueObject\PaymentGateway;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

/**
 * @extends ServiceEntityRepository<PaymentEntity>
 */
class PaymentRepository extends ServiceEntityRepository implements PaymentRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentEntity::class);
    }

    /**
     * @throws ORMException
     */
    public function save(Payment $payment): Payment
    {
        $em   = $this->getEntityManager();
        $user = $em->getReference(UserEntity::class, SymfonyUuid::fromString($payment->userId()->toRfc4122()));

        if ($payment->id() === null) {
            $entity = PaymentMapper::toDoctrine($payment, $user);
        } else {
            $entity = $this->find(SymfonyUuid::fromString($payment->id()->toRfc4122()));
            if (!$entity) {
                throw new \RuntimeException(sprintf('Payment with id %s not found', $payment->id()));
            }
            PaymentMapper::hydrate($entity, $payment, $user);
        }

        $em->persist($entity);
        $em->flush();

        return PaymentMapper::toDomain($entity);
    }

    public function findById(Uuid $id): ?Payment
    {
        $entity = $this->find(SymfonyUuid::fromString($id->toRfc4122()));

        return $entity ? PaymentMapper::toDomain($entity) : null;
    }

    public function findByUserId(Uuid $userId): array
    {
        $entities = $this->createQueryBuilder('p')
            ->join('p.user', 'u')
            ->where('u.id = :userId')
            ->setParameter('userId', SymfonyUuid::fromString($userId->toRfc4122()))
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (PaymentEntity $e): Payment => PaymentMapper::toDomain($e),
            $entities,
        );
    }

    public function findByExternalId(PaymentGateway $gateway, PaymentExternalId $externalId): ?Payment
    {
        $entity = $this->findOneBy([
            'gateway'    => $gateway->value,
            'externalId' => $externalId->value(),
        ]);

        return $entity ? PaymentMapper::toDomain($entity) : null;
    }
}
