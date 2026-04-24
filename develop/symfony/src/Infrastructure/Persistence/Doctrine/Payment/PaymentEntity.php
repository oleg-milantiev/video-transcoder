<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Payment;

use App\Domain\User\ValueObject\PaymentGateway;
use App\Domain\User\ValueObject\PaymentStatus;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'payment')]
#[ORM\Index(name: 'idx_payment_user_id', columns: ['user_id'])]
#[ORM\Index(name: 'idx_payment_user_status', columns: ['user_id', 'status'])]
#[ORM\Index(name: 'idx_payment_gateway_external', columns: ['gateway', 'external_id'])]
class PaymentEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    public ?SymfonyUuid $id = null;

    #[ORM\ManyToOne(targetEntity: UserEntity::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    public ?UserEntity $user = null;

    #[ORM\Column(length: 20)]
    public string $status = PaymentStatus::PENDING->value;

    #[ORM\Column(length: 3)]
    public string $currency = '';

    #[ORM\Column(length: 20)]
    public string $gateway = PaymentGateway::STRIPE->value;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $externalId = null;

    #[ORM\Column(length: 100, nullable: true)]
    public ?string $paymentMethod = null;

    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    public array $meta = [];

    #[ORM\Column(length: 255)]
    public string $planSnapshot = '';

    #[ORM\Column(length: 2048, nullable: true)]
    public ?string $invoiceUrl = null;

    /** Amount in minor currency units (e.g. cents). */
    #[ORM\Column(type: 'integer')]
    public int $amount = 0;

    #[ORM\Column]
    public ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $validUntil = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return sprintf(
            'Payment %s [%s/%s]',
            $this->id?->toRfc4122() ?? 'new',
            $this->gateway,
            $this->status,
        );
    }
}
