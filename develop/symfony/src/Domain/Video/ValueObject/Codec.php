<?php

namespace App\Domain\Video\ValueObject;

use InvalidArgumentException;

final class Codec
{
    private const array ALLOWED = ['h264', 'h265', 'vp9', 'av1'];

    public function __construct(
        private string $value,
    ) {
        $normalized = mb_strtolower(trim($this->value));

        if (!in_array($normalized, self::ALLOWED, true)) {
            throw new InvalidArgumentException(sprintf('Unsupported codec: %s', $this->value));
        }

        $this->value = $normalized;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isAv1(): bool
    {
        return $this->value === 'av1';
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
