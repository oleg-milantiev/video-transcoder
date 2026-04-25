<?php
declare(strict_types=1);

namespace App\Domain\User\ValueObject;

use DomainException;

/**
 * Payment amount in minor currency units (e.g. cents for USD, kopecks for RUB).
 * Must be non-negative.
 */
final readonly class PaymentAmount
{
    private int $value;

    public function __construct(int $value)
    {
        if ($value < 0) {
            throw new DomainException('Payment amount cannot be negative.');
        }

        $this->value = $value;
    }

    public function value(): int
    {
        return $this->value;
    }

    /** Converts to major currency units (e.g. dollars, rubles). */
    public function toMajorUnits(int $fraction = 100): float
    {
        if ($fraction <= 0) {
            throw new DomainException('Fraction must be positive.');
        }

        return $this->value / $fraction;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string)$this->value;
    }
}
