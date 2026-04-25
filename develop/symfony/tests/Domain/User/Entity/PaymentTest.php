<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\Entity;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Entity\Payment;
use App\Domain\User\ValueObject\PaymentAmount;
use App\Domain\User\ValueObject\PaymentCurrency;
use App\Domain\User\ValueObject\PaymentExternalId;
use App\Domain\User\ValueObject\PaymentGateway;
use App\Domain\User\ValueObject\PaymentInvoiceUrl;
use App\Domain\User\ValueObject\PaymentMethod;
use App\Domain\User\ValueObject\PaymentPlanSnapshot;
use App\Domain\User\ValueObject\PaymentStatus;
use PHPUnit\Framework\TestCase;

/**
 * Tests Payment entity — create, reconstitute, transitions, guards, accessors.
 */
final class PaymentTest extends TestCase
{
    // ── create() ─────────────────────────────────────────────────────────────

    /** create() устанавливает корректные начальные значения. */
    public function testCreateSetsInitialValues(): void
    {
        $userId = Uuid::generate();
        $before = new \DateTimeImmutable();

        $payment = Payment::create(
            userId: $userId,
            gateway: PaymentGateway::STRIPE,
            currency: new PaymentCurrency('USD'),
            amount: new PaymentAmount(9900),
            planSnapshot: new PaymentPlanSnapshot('Pro'),
        );

        $after = new \DateTimeImmutable();

        $this->assertNull($payment->id());
        $this->assertTrue($userId->equals($payment->userId()));
        $this->assertSame(PaymentStatus::PENDING, $payment->status());
        $this->assertSame(PaymentGateway::STRIPE, $payment->gateway());
        $this->assertSame('USD', $payment->currency()->value());
        $this->assertSame(9900, $payment->amount()->value());
        $this->assertSame('Pro', $payment->planSnapshot()->value());
        $this->assertNull($payment->externalId());
        $this->assertNull($payment->paymentMethod());
        $this->assertNull($payment->invoiceUrl());
        $this->assertSame([], $payment->meta());
        $this->assertNull($payment->paidAt());
        $this->assertNull($payment->validUntil());

        $this->assertGreaterThanOrEqual($before, $payment->createdAt());
        $this->assertLessThanOrEqual($after, $payment->createdAt());
    }

    // ── complete() ───────────────────────────────────────────────────────────

    /** complete() переводит статус в COMPLETED и заполняет поля. */
    public function testCompleteTransitionSucceeds(): void
    {
        $payment = $this->makePendingPayment();

        $externalId = new PaymentExternalId('pi_abc123');
        $method     = new PaymentMethod('card');
        $validUntil = new \DateTimeImmutable('+1 year');
        $invoiceUrl = new PaymentInvoiceUrl('https://example.com/invoice');

        $payment->complete(
            externalId: $externalId,
            paymentMethod: $method,
            validUntil: $validUntil,
            invoiceUrl: $invoiceUrl,
            meta: ['last4' => '4242'],
        );

        $this->assertSame(PaymentStatus::COMPLETED, $payment->status());
        $this->assertSame('pi_abc123', $payment->externalId()->value());
        $this->assertSame('card', $payment->paymentMethod()->value());
        $this->assertSame($validUntil, $payment->validUntil());
        $this->assertSame('https://example.com/invoice', $payment->invoiceUrl()->value());
        $this->assertSame(['last4' => '4242'], $payment->meta());
        $this->assertNotNull($payment->paidAt());
    }

    /** complete() нельзя вызвать дважды — бросает DomainException. */
    public function testCompleteAlreadyCompletedThrows(): void
    {
        $payment = $this->makePendingPayment();
        $payment->complete();

        $this->expectException(\DomainException::class);
        $payment->complete();
    }

    /** complete() из FAILED статуса выбрасывает DomainException. */
    public function testCompleteFailedThrows(): void
    {
        $payment = $this->makePendingPayment();
        $payment->fail();

        $this->expectException(\DomainException::class);
        $payment->complete();
    }

    // ── fail() ───────────────────────────────────────────────────────────────

    /** fail() переводит статус в FAILED; meta мерджится. */
    public function testFailTransitionSucceeds(): void
    {
        $payment = $this->makePendingPayment();
        $payment->fail(['reason' => 'card_declined']);

        $this->assertSame(PaymentStatus::FAILED, $payment->status());
        $this->assertSame(['reason' => 'card_declined'], $payment->meta());
    }

    /** fail() нельзя вызвать в статусе COMPLETED. */
    public function testFailCompletedThrows(): void
    {
        $payment = $this->makePendingPayment();
        $payment->complete();

        $this->expectException(\DomainException::class);
        $payment->fail();
    }

    // ── cancel() ─────────────────────────────────────────────────────────────

    /** cancel() переводит статус в CANCELLED. */
    public function testCancelTransitionSucceeds(): void
    {
        $payment = $this->makePendingPayment();
        $payment->cancel();

        $this->assertSame(PaymentStatus::CANCELLED, $payment->status());
    }

    /** cancel() из COMPLETED статуса выбрасывает DomainException. */
    public function testCancelCompletedThrows(): void
    {
        $payment = $this->makePendingPayment();
        $payment->complete();

        $this->expectException(\DomainException::class);
        $payment->cancel();
    }

    // ── refund() ─────────────────────────────────────────────────────────────

    /** refund() переводит COMPLETED в REFUNDED; meta мерджится. */
    public function testRefundTransitionSucceeds(): void
    {
        $payment = $this->makePendingPayment();
        $payment->complete();
        $payment->refund(['refund_id' => 'ref_xyz']);

        $this->assertSame(PaymentStatus::REFUNDED, $payment->status());
        $this->assertSame(['refund_id' => 'ref_xyz'], $payment->meta());
    }

    /** refund() нельзя вызвать из PENDING. */
    public function testRefundPendingThrows(): void
    {
        $payment = $this->makePendingPayment();

        $this->expectException(\DomainException::class);
        $payment->refund();
    }

    // ── updateExternalId / updateMeta ─────────────────────────────────────────

    /** updateExternalId() заменяет externalId. */
    public function testUpdateExternalId(): void
    {
        $payment = $this->makePendingPayment();
        $payment->updateExternalId(new PaymentExternalId('pi_new'));

        $this->assertSame('pi_new', $payment->externalId()->value());
    }

    /** updateMeta() мерджит метаданные. */
    public function testUpdateMetaMerges(): void
    {
        $payment = $this->makePendingPayment();
        $payment->updateMeta(['key1' => 'val1']);
        $payment->updateMeta(['key2' => 'val2']);

        $this->assertSame(['key1' => 'val1', 'key2' => 'val2'], $payment->meta());
    }

    // ── isActive() ───────────────────────────────────────────────────────────

    /** isActive() истинно для COMPLETED без validUntil. */
    public function testIsActiveWithoutValidUntil(): void
    {
        $payment = $this->makePendingPayment();
        $payment->complete();

        $this->assertTrue($payment->isActive());
    }

    /** isActive() истинно для COMPLETED с validUntil в будущем. */
    public function testIsActiveWithFutureValidUntil(): void
    {
        $payment = $this->makePendingPayment();
        $payment->complete(validUntil: new \DateTimeImmutable('+1 year'));

        $this->assertTrue($payment->isActive());
    }

    /** isActive() ложно для COMPLETED с истёкшим validUntil. */
    public function testIsNotActiveWithExpiredValidUntil(): void
    {
        // Используем reconstitute() с историческими датами, чтобы validUntil < paidAt не нарушал инвариант
        $created = new \DateTimeImmutable('2022-01-01');
        $paid    = new \DateTimeImmutable('2022-01-02');
        $until   = new \DateTimeImmutable('2023-01-01'); // истёкший, но >= paidAt

        $payment = Payment::reconstitute(
            userId: Uuid::generate(),
            status: PaymentStatus::COMPLETED,
            currency: new PaymentCurrency('USD'),
            gateway: PaymentGateway::STRIPE,
            amount: new PaymentAmount(9900),
            planSnapshot: new PaymentPlanSnapshot('Pro'),
            dates: \App\Domain\User\ValueObject\PaymentDates::fromPersistence($created, $paid, $until),
            externalId: null,
            paymentMethod: null,
            invoiceUrl: null,
            meta: [],
            id: Uuid::generate(),
        );

        $this->assertFalse($payment->isActive());
    }

    /** isActive() ложно для PENDING. */
    public function testIsNotActiveForPending(): void
    {
        $this->assertFalse($this->makePendingPayment()->isActive());
    }

    // ── reconstitute() ───────────────────────────────────────────────────────

    /** reconstitute() восстанавливает все поля. */
    public function testReconstituteRestoresAllFields(): void
    {
        $id      = Uuid::generate();
        $userId  = Uuid::generate();
        $created = new \DateTimeImmutable('2024-01-01');
        $paid    = new \DateTimeImmutable('2024-01-02');
        $until   = new \DateTimeImmutable('2025-01-01');

        $payment = Payment::reconstitute(
            userId: $userId,
            status: PaymentStatus::COMPLETED,
            currency: new PaymentCurrency('EUR'),
            gateway: PaymentGateway::PAYPAL,
            amount: new PaymentAmount(500),
            planSnapshot: new PaymentPlanSnapshot('Basic'),
            dates: \App\Domain\User\ValueObject\PaymentDates::fromPersistence($created, $paid, $until),
            externalId: new PaymentExternalId('ext_123'),
            paymentMethod: new PaymentMethod('bank_transfer'),
            invoiceUrl: new PaymentInvoiceUrl('https://example.com/inv'),
            meta: ['key' => 'val'],
            id: $id,
        );

        $this->assertTrue($id->equals($payment->id()));
        $this->assertSame(PaymentStatus::COMPLETED, $payment->status());
        $this->assertSame('EUR', $payment->currency()->value());
        $this->assertSame(PaymentGateway::PAYPAL, $payment->gateway());
        $this->assertSame(500, $payment->amount()->value());
        $this->assertSame('Basic', $payment->planSnapshot()->value());
        $this->assertSame($created, $payment->createdAt());
        $this->assertSame($paid, $payment->paidAt());
        $this->assertSame($until, $payment->validUntil());
        $this->assertSame('ext_123', $payment->externalId()->value());
        $this->assertSame('bank_transfer', $payment->paymentMethod()->value());
        $this->assertSame('https://example.com/inv', $payment->invoiceUrl()->value());
        $this->assertSame(['key' => 'val'], $payment->meta());
    }

    // ── __toString() ─────────────────────────────────────────────────────────

    /** __toString() содержит gateway и status. */
    public function testToStringContainsGatewayAndStatus(): void
    {
        $payment = $this->makePendingPayment();
        $str     = (string) $payment;

        $this->assertStringContainsString('stripe', $str);
        $this->assertStringContainsString('pending', $str);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function makePendingPayment(): Payment
    {
        return Payment::create(
            userId: Uuid::generate(),
            gateway: PaymentGateway::STRIPE,
            currency: new PaymentCurrency('USD'),
            amount: new PaymentAmount(9900),
            planSnapshot: new PaymentPlanSnapshot('Pro'),
        );
    }
}
