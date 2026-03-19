<?php

namespace App\Domain\Video\Exception;

final class InvalidVideoDates extends \DomainException
{
    public static function updatedAtBeforeCreatedAt(): self
    {
        return new self('updatedAt cannot be earlier than createdAt.');
    }
}

