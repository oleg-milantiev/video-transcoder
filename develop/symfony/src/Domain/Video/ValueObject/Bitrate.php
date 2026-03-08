<?php

namespace App\Domain\Video\ValueObject;

final readonly class Bitrate
{
    private const int MAXIMUM_BITRATE = 200 * 1024 * 1024; // 200 Mbps

    public function __construct(
        private int $value,
    ) {
        if ($this->value < 0) {
            throw new \InvalidArgumentException('Bitrate cannot be negative.');
        }
        if ($this->value > self::MAXIMUM_BITRATE) {
            throw new \InvalidArgumentException('Bitrate must be less 200 Mbpi.');
        }
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
