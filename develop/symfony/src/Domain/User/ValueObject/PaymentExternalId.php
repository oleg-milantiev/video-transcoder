<?php
declare(strict_types=1);

namespace App\Domain\User\ValueObject;

use DomainException;

/**
 * External payment ID assigned by the payment gateway (e.g. Stripe payment_intent ID).
 */
final readonly class PaymentExternalId
{
    private const int MAX_LENGTH = 255;

    private string $value;

    public function __construct(string $value)
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new DomainException('Payment external ID cannot be empty.');
        }

        if (mb_strlen($normalized) > self::MAX_LENGTH) {
            throw new DomainException('Payment external ID must not exceed 255 characters.');
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
