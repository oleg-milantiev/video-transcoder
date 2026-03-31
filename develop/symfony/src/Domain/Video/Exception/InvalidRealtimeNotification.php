<?php
declare(strict_types=1);

namespace App\Domain\Video\Exception;

final class InvalidRealtimeNotification extends \DomainException
{
    public static function titleEmpty(): self
    {
        return new self('Realtime notification title cannot be empty.');
    }

    public static function titleTooLong(int $maxLength): self
    {
        return new self(sprintf('Realtime notification title cannot be longer than %d characters.', $maxLength));
    }

    public static function htmlEmpty(): self
    {
        return new self('Realtime notification html cannot be empty.');
    }

    public static function timerOutOfRange(int $timerMs, int $min, int $max): self
    {
        return new self(sprintf('Realtime notification timer must be between %d and %d ms, got %d.', $min, $max, $timerMs));
    }

    public static function invalidImageUrl(string $imageUrl): self
    {
        return new self(sprintf('Realtime notification image URL is invalid: %s', $imageUrl));
    }
}

