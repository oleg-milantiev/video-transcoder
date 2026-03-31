<?php
declare(strict_types=1);

namespace App\Domain\Video\Exception;

final class InvalidVideoDates extends \DomainException
{
    public static function updatedAtBeforeCreatedAt(): self
    {
        return new self('updatedAt cannot be earlier than createdAt.');
    }
}

