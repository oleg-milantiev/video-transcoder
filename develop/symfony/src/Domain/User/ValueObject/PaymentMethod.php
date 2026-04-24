<?php
declare(strict_types=1);

namespace App\Domain\User\ValueObject;

/**
 * Payment method identifier, e.g. 'card', 'bank_transfer', 'wallet'.
 */
final readonly class PaymentMethod
{
    private const int MAX_LENGTH = 100;

    private string $value;

    public function __construct(string $value)
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new \DomainException('Payment method cannot be empty.');
        }

        if (mb_strlen($normalized) > self::MAX_LENGTH) {
            throw new \DomainException('Payment method must not exceed 100 characters.');
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
