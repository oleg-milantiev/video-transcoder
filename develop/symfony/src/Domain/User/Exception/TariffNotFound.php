<?php
declare(strict_types=1);

namespace App\Domain\User\Exception;

final class TariffNotFound extends \DomainException
{
    public static function forUser(string $userId): self
    {
        return new self(sprintf('Tariff not found for user: %s', $userId));
    }
}

