<?php
declare(strict_types=1);

namespace App\Domain\Shared\Exception;

class InvalidUuidException extends \DomainException
{
    public static function invalidFormat(string $uuid): self
    {
        return new self(sprintf('Invalid UUID v4 format: "%s"', $uuid));
    }
}
