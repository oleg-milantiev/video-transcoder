<?php

namespace App\Domain\Video\ValueObject;

enum TaskStatus: int
{
    case PENDING = 1;
    case STARTING = 2;
    case PROCESSING = 3;
    case COMPLETED = 4;
    case FAILED = 5;
    case CANCELLED = 6;
    case DELETED = 7;

    public const array NAMES = [
        self::PENDING->value => self::PENDING->name,
        self::STARTING->value => self::STARTING->name,
        self::PROCESSING->value => self::PROCESSING->name,
        self::COMPLETED->value => self::COMPLETED->name,
        self::FAILED->value => self::FAILED->name,
        self::CANCELLED->value => self::CANCELLED->name,
        self::DELETED->value => self::DELETED->name,
    ];

    public static function pending(): TaskStatus
    {
        return self::PENDING;
    }

    public static function starting(): TaskStatus
    {
        return self::STARTING;
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

    public static function deleted(): TaskStatus
    {
        return self::DELETED;
    }

    public function canBeStarted(): bool
    {
        return $this === self::STARTING;
    }

    public function canBeDeleted(): bool
    {
        return $this !== self::PENDING && $this !== self::STARTING && $this !== self::PROCESSING;
    }

    public function canBeRestarted(): bool
    {
        return $this === self::CANCELLED || $this === self::FAILED;
    }

    public function isTranscoding(): bool
    {
        return $this === self::PENDING || $this == self::STARTING || $this === self::PROCESSING;
    }

    public function isDeleted(): bool
    {
        return $this === self::DELETED;
    }

    public function isFinished(): bool
    {
        return $this === self::COMPLETED || $this === self::FAILED || $this === self::CANCELLED || $this === self::DELETED;
    }
}
