<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\Exception\InvalidPaymentDates;
use App\Domain\User\ValueObject\PaymentDates;
use PHPUnit\Framework\TestCase;

/**
 * Tests PaymentDates VO — create, fromPersistence, markPaid, инварианты дат.
 */
final class PaymentDatesTest extends TestCase
{
    /** create() устанавливает createdAt в текущее время; paidAt и validUntil null. */
    public function testCreateSetsCreatedAtNow(): void
    {
        $before = new \DateTimeImmutable();
        $dates  = PaymentDates::create();
        $after  = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $dates->createdAt());
        $this->assertLessThanOrEqual($after, $dates->createdAt());
        $this->assertNull($dates->paidAt());
        $this->assertNull($dates->validUntil());
    }

    /** create() принимает явно переданный createdAt. */
    public function testCreateWithExplicitCreatedAt(): void
    {
        $dt    = new \DateTimeImmutable('2024-01-01');
        $dates = PaymentDates::create($dt);
        $this->assertSame($dt, $dates->createdAt());
    }

    /** fromPersistence() восстанавливает все поля из базы. */
    public function testFromPersistence(): void
    {
        $created = new \DateTimeImmutable('2024-01-01');
        $paid    = new \DateTimeImmutable('2024-01-02');
        $until   = new \DateTimeImmutable('2025-01-01');

        $dates = PaymentDates::fromPersistence($created, $paid, $until);

        $this->assertSame($created, $dates->createdAt());
        $this->assertSame($paid,    $dates->paidAt());
        $this->assertSame($until,   $dates->validUntil());
    }

    /** markPaid() устанавливает paidAt и validUntil; createdAt не меняется. */
    public function testMarkPaid(): void
    {
        $created = new \DateTimeImmutable('2024-01-01');
        $dates   = PaymentDates::create($created);

        $paid  = new \DateTimeImmutable('2024-01-02');
        $until = new \DateTimeImmutable('2025-01-01');

        $marked = $dates->markPaid($paid, $until);

        $this->assertSame($created, $marked->createdAt());
        $this->assertSame($paid,    $marked->paidAt());
        $this->assertSame($until,   $marked->validUntil());
    }

    /** markPaid() без аргументов устанавливает paidAt = now; validUntil остаётся null. */
    public function testMarkPaidWithDefaults(): void
    {
        $before = new \DateTimeImmutable();
        $marked = PaymentDates::create()->markPaid();
        $after  = new \DateTimeImmutable();

        $this->assertNotNull($marked->paidAt());
        $this->assertGreaterThanOrEqual($before, $marked->paidAt());
        $this->assertLessThanOrEqual($after, $marked->paidAt());
        $this->assertNull($marked->validUntil());
    }

    /** paidAt раньше createdAt выбрасывает InvalidPaymentDates. */
    public function testPaidAtBeforeCreatedAtThrows(): void
    {
        $this->expectException(InvalidPaymentDates::class);
        $this->expectExceptionMessage('paidAt');

        $created = new \DateTimeImmutable('2024-06-01');
        $paid    = new \DateTimeImmutable('2024-05-01');

        PaymentDates::fromPersistence($created, $paid, null);
    }

    /** validUntil раньше createdAt выбрасывает InvalidPaymentDates. */
    public function testValidUntilBeforeCreatedAtThrows(): void
    {
        $this->expectException(InvalidPaymentDates::class);
        $this->expectExceptionMessage('validUntil');

        $created = new \DateTimeImmutable('2024-06-01');
        $until   = new \DateTimeImmutable('2024-05-01');

        PaymentDates::fromPersistence($created, null, $until);
    }

    /** validUntil раньше paidAt выбрасывает InvalidPaymentDates. */
    public function testValidUntilBeforePaidAtThrows(): void
    {
        $this->expectException(InvalidPaymentDates::class);
        $this->expectExceptionMessage('validUntil');

        $created = new \DateTimeImmutable('2024-01-01');
        $paid    = new \DateTimeImmutable('2024-06-01');
        $until   = new \DateTimeImmutable('2024-05-01');

        PaymentDates::fromPersistence($created, $paid, $until);
    }

    /** VO иммутабельно: markPaid() возвращает новый объект. */
    public function testMarkPaidReturnsNewInstance(): void
    {
        $dates  = PaymentDates::create(new \DateTimeImmutable('2024-01-01'));
        $marked = $dates->markPaid(new \DateTimeImmutable('2024-01-02'));

        $this->assertNotSame($dates, $marked);
    }
}
