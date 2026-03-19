<?php

namespace App\Domain\Video\ValueObject;

use App\Domain\Video\Exception\InvalidVideoTitle;

final readonly class VideoTitle
{
    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if (mb_strlen($trimmed) < 1) {
            throw InvalidVideoTitle::empty();
        }

        if (mb_strlen($trimmed) > 255) {
            throw InvalidVideoTitle::tooLong(255);
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
