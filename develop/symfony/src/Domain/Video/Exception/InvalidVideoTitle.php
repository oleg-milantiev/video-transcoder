<?php

namespace App\Domain\Video\Exception;

final class InvalidVideoTitle extends \DomainException
{
    public static function empty(): self
    {
        return new self('Video title cannot be empty.');
    }

    public static function tooLong(int $maxLength): self
    {
        return new self(sprintf('Video title must be less than %d characters long.', $maxLength));
    }
}

