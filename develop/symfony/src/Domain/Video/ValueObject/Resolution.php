<?php

namespace App\Domain\Video\ValueObject;

final readonly class Resolution
{
    public function __construct(
        private int $width,
        private int $height,
    ) {
        if ($this->width <= 0 || $this->height <= 0) {
            throw new \InvalidArgumentException('Resolution dimensions must be positive integers.');
        }
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

//    public function isVertical(): bool
//    {
//        return $this->height > $this->width;
//    }
//
//    public function isHorizontal(): bool
//    {
//        return $this->height < $this->width;
//    }
//
//    public function isSquare(): bool
//    {
//        return $this->height === $this->width;
//    }

    public function is4k(): bool
    {
        return $this->width >= 3840 || $this->height >= 2160;
    }

    public function equals(self $other): bool
    {
        return $this->width === $other->width && $this->height === $other->height;
    }

    public function __toString(): string
    {
        return sprintf('%dx%d', $this->width, $this->height);
    }
}
