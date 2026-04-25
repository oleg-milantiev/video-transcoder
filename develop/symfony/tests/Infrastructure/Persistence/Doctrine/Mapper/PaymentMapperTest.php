<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\User\Entity\Payment;
use App\Domain\User\ValueObject\PaymentAmount;
use App\Domain\User\ValueObject\PaymentCurrency;
use App\Domain\User\ValueObject\PaymentGateway;
use App\Domain\User\ValueObject\PaymentPlanSnapshot;
use App\Infrastructure\Persistence\Doctrine\Payment\PaymentEntity;
use App\Infrastructure\Persistence\Doctrine\Payment\PaymentMapper;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Domain\Shared\ValueObject\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

final class PaymentMapperTest extends TestCase
{
    private UserEntity $userEntity;
    private PaymentEntity $paymentEntity;

    protected function setUp(): void
    {
        $this->userEntity = new UserEntity();
        $this->userEntity->id = SymfonyUuid::fromString('11111111-1111-4111-8111-111111111111');

        $this->paymentEntity = new PaymentEntity();
        $this->paymentEntity->id = SymfonyUuid::fromString('66666666-6666-4666-8666-666666666666');
        $this->paymentEntity->user = $this->userEntity;
        $this->paymentEntity->status = 'pending';
        $this->paymentEntity->currency = 'USD';
        $this->paymentEntity->gateway = 'stripe';
        $this->paymentEntity->externalId = 'pi_test_123';
        $this->paymentEntity->paymentMethod = 'card';
        $this->paymentEntity->meta = ['ip' => '127.0.0.1'];
        $this->paymentEntity->planSnapshot = 'Pro';
        $this->paymentEntity->invoiceUrl = 'https://example.com/invoice/123';
        $this->paymentEntity->amount = 1999;
        $this->paymentEntity->createdAt = new DateTimeImmutable('2024-01-01 10:00:00');
        $this->paymentEntity->paidAt = null;
        $this->paymentEntity->validUntil = null;
    }

    public function testToDomainMapsAllFields(): void
    {
        $payment = PaymentMapper::toDomain($this->paymentEntity);

        self::assertInstanceOf(Payment::class, $payment);
        self::assertSame('66666666-6666-4666-8666-666666666666', $payment->id()->toRfc4122());
        self::assertSame('11111111-1111-4111-8111-111111111111', $payment->userId()->toRfc4122());
        self::assertSame('pending', $payment->status()->value);
        self::assertSame('USD', $payment->currency()->value());
        self::assertSame('stripe', $payment->gateway()->value);
        self::assertSame('pi_test_123', $payment->externalId()->value());
        self::assertSame('card', $payment->paymentMethod()->value());
        self::assertSame(['ip' => '127.0.0.1'], $payment->meta());
        self::assertSame('Pro', $payment->planSnapshot()->value());
        self::assertSame('https://example.com/invoice/123', $payment->invoiceUrl()->value());
        self::assertSame(1999, $payment->amount()->value());
        self::assertNull($payment->paidAt());
        self::assertNull($payment->validUntil());
    }

    public function testToDomainWithNullOptionalFields(): void
    {
        $this->paymentEntity->externalId = null;
        $this->paymentEntity->paymentMethod = null;
        $this->paymentEntity->invoiceUrl = null;

        $payment = PaymentMapper::toDomain($this->paymentEntity);

        self::assertNull($payment->externalId());
        self::assertNull($payment->paymentMethod());
        self::assertNull($payment->invoiceUrl());
    }

    public function testToDomainWithPaidDates(): void
    {
        $paidAt = new DateTimeImmutable('2024-06-01 12:00:00');
        $validUntil = new DateTimeImmutable('2025-06-01 12:00:00');
        $this->paymentEntity->status = 'completed';
        $this->paymentEntity->paidAt = $paidAt;
        $this->paymentEntity->validUntil = $validUntil;

        $payment = PaymentMapper::toDomain($this->paymentEntity);

        self::assertEquals($paidAt, $payment->paidAt());
        self::assertEquals($validUntil, $payment->validUntil());
    }

    public function testToDoctrineCreatesEntityWithCorrectFields(): void
    {
        $userId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $payment = Payment::create(
            userId: $userId,
            gateway: PaymentGateway::STRIPE,
            currency: new PaymentCurrency('EUR'),
            amount: new PaymentAmount(999),
            planSnapshot: new PaymentPlanSnapshot('Basic'),
        );

        $entity = PaymentMapper::toDoctrine($payment, $this->userEntity);

        self::assertInstanceOf(PaymentEntity::class, $entity);
        self::assertSame('pending', $entity->status);
        self::assertSame('EUR', $entity->currency);
        self::assertSame('stripe', $entity->gateway);
        self::assertSame(999, $entity->amount);
        self::assertSame('Basic', $entity->planSnapshot);
        self::assertSame($this->userEntity, $entity->user);
    }

    public function testHydrateUpdatesExistingEntity(): void
    {
        $userId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $payment = Payment::create(
            userId: $userId,
            gateway: PaymentGateway::STRIPE,
            currency: new PaymentCurrency('USD'),
            amount: new PaymentAmount(500),
            planSnapshot: new PaymentPlanSnapshot('Free'),
        );

        $target = new PaymentEntity();
        PaymentMapper::hydrate($target, $payment, $this->userEntity);

        self::assertSame('pending', $target->status);
        self::assertSame('USD', $target->currency);
        self::assertSame($this->userEntity, $target->user);
    }
}
