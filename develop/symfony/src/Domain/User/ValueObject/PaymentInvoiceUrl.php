<?php
declare(strict_types=1);

namespace App\Domain\User\ValueObject;

/**
 * URL to the hosted payment invoice/receipt.
 */
final readonly class PaymentInvoiceUrl
{
    private const int MAX_LENGTH = 2048;

    private string $value;

    public function __construct(string $value)
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new \DomainException('Payment invoice URL cannot be empty.');
        }

        if (mb_strlen($normalized) > self::MAX_LENGTH) {
            throw new \DomainException('Payment invoice URL must not exceed 2048 characters.');
        }

        if (!filter_var($normalized, FILTER_VALIDATE_URL)) {
            throw new \DomainException('Payment invoice URL has invalid format.');
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
