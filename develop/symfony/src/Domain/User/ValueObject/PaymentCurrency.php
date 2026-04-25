<?php
declare(strict_types=1);

namespace App\Domain\User\ValueObject;

use DomainException;

/**
 * ISO 4217 currency code, e.g. USD, EUR, RUB.
 */
final readonly class PaymentCurrency
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtoupper(trim($value));

        if ($normalized === '') {
            throw new DomainException('Payment currency cannot be empty.');
        }

        if (!preg_match('/^[A-Z]{3}$/', $normalized)) {
            throw new DomainException('Payment currency must be a 3-letter ISO 4217 code (e.g. USD, EUR, RUB).');
        }

        $this->value = $normalized;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
