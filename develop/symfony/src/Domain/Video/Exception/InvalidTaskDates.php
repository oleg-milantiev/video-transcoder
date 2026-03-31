<?php
declare(strict_types=1);

namespace App\Domain\Video\Exception;

final class InvalidTaskDates extends \DomainException
{
    public static function startedAtBeforeCreatedAt(): self
    {
        return new self('startedAt cannot be earlier than createdAt.');
    }

    public static function updatedAtBeforeCreatedAt(): self
    {
        return new self('updatedAt cannot be earlier than createdAt.');
    }

    public static function updatedAtBeforeStartedAt(): self
    {
        return new self('updatedAt cannot be earlier than startedAt.');
    }

    public static function alreadyStarted(): self
    {
        return new self('Task is already started.');
    }
}

