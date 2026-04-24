<?php
declare(strict_types=1);

namespace App\Domain\User\Entity;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Exception\PaymentNotFound;
use App\Domain\User\ValueObject\PaymentAmount;
use App\Domain\User\ValueObject\PaymentCurrency;
use App\Domain\User\ValueObject\PaymentDates;
use App\Domain\User\ValueObject\PaymentExternalId;
use App\Domain\User\ValueObject\PaymentGateway;
use App\Domain\User\ValueObject\PaymentInvoiceUrl;
use App\Domain\User\ValueObject\PaymentMethod;
use App\Domain\User\ValueObject\PaymentPlanSnapshot;
use App\Domain\User\ValueObject\PaymentStatus;

class Payment
{
    private ?Uuid $id;
    private Uuid $userId;
    private PaymentStatus $status;
    private PaymentCurrency $currency;
    private PaymentGateway $gateway;
    private ?PaymentExternalId $externalId;
    private ?PaymentMethod $paymentMethod;
    private array $meta;
    private PaymentPlanSnapshot $planSnapshot;
    private ?PaymentInvoiceUrl $invoiceUrl;
    private PaymentAmount $amount;
    private PaymentDates $dates;

    private function __construct(
        Uuid $userId,
        PaymentStatus $status,
        PaymentCurrency $currency,
        PaymentGateway $gateway,
        PaymentAmount $amount,
        PaymentPlanSnapshot $planSnapshot,
        PaymentDates $dates,
        ?PaymentExternalId $externalId,
        ?PaymentMethod $paymentMethod,
        ?PaymentInvoiceUrl $invoiceUrl,
        array $meta,
        ?Uuid $id,
    ) {
        $this->id            = $id;
        $this->userId        = $userId;
        $this->status        = $status;
        $this->currency      = $currency;
        $this->gateway       = $gateway;
        $this->amount        = $amount;
        $this->planSnapshot  = $planSnapshot;
        $this->dates         = $dates;
        $this->externalId    = $externalId;
        $this->paymentMethod = $paymentMethod;
        $this->invoiceUrl    = $invoiceUrl;
        $this->meta          = $meta;
    }

    public static function create(
        Uuid $userId,
        PaymentGateway $gateway,
        PaymentCurrency $currency,
        PaymentAmount $amount,
        PaymentPlanSnapshot $planSnapshot,
    ): self {
        return new self(
            userId: $userId,
            status: PaymentStatus::pending(),
            currency: $currency,
            gateway: $gateway,
            amount: $amount,
            planSnapshot: $planSnapshot,
            dates: PaymentDates::create(),
            externalId: null,
            paymentMethod: null,
            invoiceUrl: null,
            meta: [],
            id: null,
        );
    }

    public static function reconstitute(
        Uuid $userId,
        PaymentStatus $status,
        PaymentCurrency $currency,
        PaymentGateway $gateway,
        PaymentAmount $amount,
        PaymentPlanSnapshot $planSnapshot,
        PaymentDates $dates,
        ?PaymentExternalId $externalId,
        ?PaymentMethod $paymentMethod,
        ?PaymentInvoiceUrl $invoiceUrl,
        array $meta,
        Uuid $id,
    ): self {
        return new self(
            $userId, $status, $currency, $gateway, $amount,
            $planSnapshot, $dates,
            $externalId, $paymentMethod, $invoiceUrl, $meta, $id,
        );
    }

    // ── Transitions ──────────────────────────────────────────────────────────

    public function complete(
        ?PaymentExternalId $externalId = null,
        ?PaymentMethod $paymentMethod = null,
        ?\DateTimeImmutable $validUntil = null,
        ?PaymentInvoiceUrl $invoiceUrl = null,
        array $meta = [],
    ): void {
        if (!$this->status->canBeCompleted()) {
            throw new \DomainException(sprintf(
                'Payment in status "%s" cannot be completed.', $this->status->value
            ));
        }

        $this->status        = PaymentStatus::completed();
        $this->externalId    = $externalId ?? $this->externalId;
        $this->paymentMethod = $paymentMethod ?? $this->paymentMethod;
        $this->invoiceUrl    = $invoiceUrl ?? $this->invoiceUrl;
        $this->dates         = $this->dates->markPaid(new \DateTimeImmutable(), $validUntil);
        $this->meta          = array_merge($this->meta, $meta);
    }

    public function fail(array $meta = []): void
    {
        if (!$this->status->canBeFailed()) {
            throw new \DomainException(sprintf(
                'Payment in status "%s" cannot be failed.', $this->status->value
            ));
        }

        $this->status = PaymentStatus::failed();
        $this->meta   = array_merge($this->meta, $meta);
    }

    public function cancel(): void
    {
        if (!$this->status->canBeCancelled()) {
            throw new \DomainException(sprintf(
                'Payment in status "%s" cannot be cancelled.', $this->status->value
            ));
        }

        $this->status = PaymentStatus::cancelled();
    }

    public function refund(array $meta = []): void
    {
        if (!$this->status->canBeRefunded()) {
            throw new \DomainException(sprintf(
                'Payment in status "%s" cannot be refunded.', $this->status->value
            ));
        }

        $this->status = PaymentStatus::refunded();
        $this->meta   = array_merge($this->meta, $meta);
    }

    // ── Mutations ────────────────────────────────────────────────────────────

    public function updateExternalId(PaymentExternalId $externalId): void
    {
        $this->externalId = $externalId;
    }

    public function updateMeta(array $meta): void
    {
        $this->meta = array_merge($this->meta, $meta);
    }

    // ── Accessors ────────────────────────────────────────────────────────────

    public function id(): ?Uuid
    {
        return $this->id;
    }

    public function userId(): Uuid
    {
        return $this->userId;
    }

    public function status(): PaymentStatus
    {
        return $this->status;
    }

    public function currency(): PaymentCurrency
    {
        return $this->currency;
    }

    public function gateway(): PaymentGateway
    {
        return $this->gateway;
    }

    public function externalId(): ?PaymentExternalId
    {
        return $this->externalId;
    }

    public function paymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function meta(): array
    {
        return $this->meta;
    }

    public function planSnapshot(): PaymentPlanSnapshot
    {
        return $this->planSnapshot;
    }

    public function invoiceUrl(): ?PaymentInvoiceUrl
    {
        return $this->invoiceUrl;
    }

    public function amount(): PaymentAmount
    {
        return $this->amount;
    }

    public function dates(): PaymentDates
    {
        return $this->dates;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->dates->createdAt();
    }

    public function paidAt(): ?\DateTimeImmutable
    {
        return $this->dates->paidAt();
    }

    public function validUntil(): ?\DateTimeImmutable
    {
        return $this->dates->validUntil();
    }

    public function isActive(): bool
    {
        if (!$this->status->isCompleted()) {
            return false;
        }

        $validUntil = $this->dates->validUntil();

        return $validUntil === null || $validUntil >= new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return sprintf(
            'Payment %s [%s/%s]',
            $this->id?->toRfc4122() ?? 'new',
            $this->gateway->value,
            $this->status->value,
        );
    }
}
