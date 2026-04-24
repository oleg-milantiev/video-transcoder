<?php
declare(strict_types=1);

namespace App\Domain\User\Exception;

final class InvalidPaymentDates extends \DomainException
{
    public static function paidAtBeforeCreatedAt(): self
    {
        return new self('Payment paidAt cannot be before createdAt.');
    }

    public static function validUntilBeforeCreatedAt(): self
    {
        return new self('Payment validUntil cannot be before createdAt.');
    }

    public static function validUntilBeforePaidAt(): self
    {
        return new self('Payment validUntil cannot be before paidAt.');
    }
}
