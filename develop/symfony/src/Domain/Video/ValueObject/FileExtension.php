<?php

namespace App\Domain\Video\ValueObject;

use App\Domain\Video\Exception\IncompatibleVideoFormat;

final readonly class FileExtension
{
    private const array ALLOWED = ['mp4', 'mkv', 'avi', 'mov'];
    private string $value;

    public function __construct(string $value)
    {
        $normalized = mb_strtolower(trim($value));

        if (!in_array($normalized, self::ALLOWED, true)) {
            throw IncompatibleVideoFormat::fromValue(sprintf('Unsupported file extension: %s', $value));
        }

        $this->value = $normalized;
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
