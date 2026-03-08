<?php

namespace App\Domain\Video\ValueObject;

final readonly class VideoTitle
{
    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if (mb_strlen($trimmed) < 1) {
            throw new \InvalidArgumentException('Video title cannot be empty.');
        }

        if (mb_strlen($trimmed) > 255) {
            throw new \InvalidArgumentException('Video title must be less than 255 characters long.');
        }

        $this->value = $trimmed;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
