<?php
declare(strict_types=1);

namespace App\Domain\Video\ValueObject;

use App\Domain\Video\Exception\IncompatibleVideoFormat;

final readonly class Bitrate
{
    private const float MAXIMUM_BITRATE = 200.0; // Mbps

    public function __construct(
        private float $value,
    ) {
        if ($this->value < 0.0) {
            throw IncompatibleVideoFormat::fromValue('Bitrate cannot be negative.');
        }
        if ($this->value > self::MAXIMUM_BITRATE) {
            throw IncompatibleVideoFormat::fromValue('Bitrate must be less than or equal to '. self::MAXIMUM_BITRATE .' Mbps.');
        }
    }

    public function value(): float
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
