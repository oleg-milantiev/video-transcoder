<?php

namespace App\Domain\Video\ValueObject;

final readonly class PresetName
{
    private string $value;

    public function __construct(
        string $value,
    ) {
        $trimmed = trim($value);

        if (mb_strlen($trimmed) < 3) {
            throw new \InvalidArgumentException('Preset name must be at least 3 characters long.');
        }

        if (mb_strlen($trimmed) > 255) {
            throw new \InvalidArgumentException('Preset name must be less than 255 characters long.');
        }

        $this->value = $trimmed;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
