<?php

namespace App\Domain\Video\ValueObject;

use InvalidArgumentException;

final readonly class Progress
{
    public function __construct(
        private int $value,
    ) {
        if ($this->value < 0 || $this->value > 100) {
            // TODO DDD new exception
            throw new \DomainException('Progress must be between 0 and 100.');
        }
    }

    public function value(): int
    {
        return $this->value;
    }

    public function isComplete(): bool
    {
        return $this->value === 100;
    }
}
