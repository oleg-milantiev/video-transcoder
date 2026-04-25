<?php
declare(strict_types=1);

namespace App\Domain\User\ValueObject;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
    case CANCELLED = 'cancelled';

    public const array NAMES = [
        self::PENDING->value => self::PENDING->name,
        self::COMPLETED->value => self::COMPLETED->name,
        self::FAILED->value => self::FAILED->name,
        self::REFUNDED->value => self::REFUNDED->name,
        self::CANCELLED->value => self::CANCELLED->name,
    ];

    public static function pending(): self
    {
        return self::PENDING;
    }

    public static function completed(): self
    {
        return self::COMPLETED;
    }

    public static function failed(): self
    {
        return self::FAILED;
    }

    public static function refunded(): self
    {
        return self::REFUNDED;
    }

    public static function cancelled(): self
    {
        return self::CANCELLED;
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }

    public function isRefunded(): bool
    {
        return $this === self::REFUNDED;
    }

    public function isCancelled(): bool
    {
        return $this === self::CANCELLED;
    }

    /** Terminal statuses cannot transition further. */
    public function isTerminal(): bool
    {
        return $this === self::FAILED
            || $this === self::REFUNDED
            || $this === self::CANCELLED;
    }

    public function canBeCompleted(): bool
    {
        return $this === self::PENDING;
    }

    public function canBeFailed(): bool
    {
        return $this === self::PENDING;
    }

    public function canBeCancelled(): bool
    {
        return $this === self::PENDING;
    }

    public function canBeRefunded(): bool
    {
        return $this === self::COMPLETED;
    }
}
