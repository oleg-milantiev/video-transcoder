<?php

namespace App\Domain\Video\ValueObject;

use InvalidArgumentException;

enum TaskStatus: int
{
    case PENDING = 1;
    case PROCESSING = 2;
    case COMPLETED = 3;
    case FAILED = 4;
    case CANCELLED = 5;

    public const array NAMES = [
        self::PENDING->value => self::PENDING->name,
        self::PROCESSING->value => self::PROCESSING->name,
        self::COMPLETED->value => self::COMPLETED->name,
        self::FAILED->value => self::FAILED->name,
        self::CANCELLED->value => self::CANCELLED->name,
    ];

    public static function pending(): TaskStatus
    {
        return self::PENDING;
    }

    public static function processing(): TaskStatus
    {
        return self::PROCESSING;
    }

    public static function completed(): TaskStatus
    {
        return self::COMPLETED;
    }

    public static function failed(): TaskStatus
    {
        return self::FAILED;
    }

    public static function cancelled(): TaskStatus
    {
        return self::CANCELLED;
    }

    public function canBeStarted(): bool
    {
        return $this === self::PENDING;
    }

    public function isFinished(): bool
    {
        return $this === self::COMPLETED || $this === self::FAILED || $this === self::CANCELLED;
    }
}
