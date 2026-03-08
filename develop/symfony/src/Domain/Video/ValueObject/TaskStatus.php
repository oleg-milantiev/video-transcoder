<?php

namespace App\Domain\Video\ValueObject;

use InvalidArgumentException;

enum TaskStatus: int
{
    case STATUS_PENDING = 1;
    case STATUS_PROCESSING = 2;
    case STATUS_COMPLETED = 3;
    case STATUS_FAILED = 4;

    public static function pending(): TaskStatus
    {
        return self::STATUS_PENDING;
    }

    public static function processing(): TaskStatus
    {
        return self::STATUS_PROCESSING;
    }

    public static function completed(): TaskStatus
    {
        return self::STATUS_COMPLETED;
    }

    public static function failed(): TaskStatus
    {
        return self::STATUS_FAILED;
    }

    public function canBeStarted(): bool
    {
        return $this === self::STATUS_PENDING;
    }

    public function isFinished(): bool
    {
        return $this === self::STATUS_COMPLETED || $this === self::STATUS_FAILED;
    }
}
