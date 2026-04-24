<?php
declare(strict_types=1);

namespace App\Domain\User\Exception;

final class PaymentNotFound extends \DomainException
{
    public static function byId(string $paymentId): self
    {
        return new self(sprintf('Payment not found: %s', $paymentId));
    }
}
