<?php
declare(strict_types=1);

namespace App\Domain\Video\ValueObject;

use App\Domain\Video\Exception\UnsupportedCodec;

final class Codec
{
    private const array ALLOWED = ['h264', 'h265', 'vp9', 'av1'];

    public function __construct(
        private string $value,
    ) {
        $normalized = mb_strtolower(trim($this->value));

        if (!in_array($normalized, self::ALLOWED, true)) {
            throw UnsupportedCodec::fromValue($this->value);
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
