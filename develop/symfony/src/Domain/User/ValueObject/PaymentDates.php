<?php
declare(strict_types=1);

namespace App\Domain\User\ValueObject;

use App\Domain\User\Exception\InvalidPaymentDates;

final readonly class PaymentDates
{
    private function __construct(
        private \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $paidAt,
        private ?\DateTimeImmutable $validUntil,
    ) {
        if ($this->paidAt !== null && $this->paidAt < $this->createdAt) {
            throw InvalidPaymentDates::paidAtBeforeCreatedAt();
        }

        if ($this->validUntil !== null && $this->validUntil < $this->createdAt) {
            throw InvalidPaymentDates::validUntilBeforeCreatedAt();
        }

        if ($this->paidAt !== null && $this->validUntil !== null && $this->validUntil < $this->paidAt) {
            throw InvalidPaymentDates::validUntilBeforePaidAt();
        }
    }

    public static function create(?\DateTimeImmutable $createdAt = null): self
    {
        return new self($createdAt ?? new \DateTimeImmutable(), null, null);
    }

    public static function fromPersistence(
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $paidAt,
        ?\DateTimeImmutable $validUntil,
    ): self {
        return new self($createdAt, $paidAt, $validUntil);
    }

    public function markPaid(
        ?\DateTimeImmutable $paidAt = null,
        ?\DateTimeImmutable $validUntil = null,
    ): self {
        return new self($this->createdAt, $paidAt ?? new \DateTimeImmutable(), $validUntil);
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function paidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function validUntil(): ?\DateTimeImmutable
    {
        return $this->validUntil;
    }
}
