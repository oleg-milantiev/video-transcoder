<?php
declare(strict_types=1);

namespace App\Domain\Video\ValueObject;

use App\Domain\Video\Exception\InvalidProgress;

final readonly class Progress
{
    public function __construct(
        private int $value,
    ) {
        if ($this->value < 0 || $this->value > 100) {
            throw InvalidProgress::outOfRange($this->value);
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
