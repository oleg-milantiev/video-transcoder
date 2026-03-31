<?php
declare(strict_types=1);

namespace App\Domain\Video\Exception;

final class InvalidProgress extends \DomainException
{
    public static function outOfRange(int $value): self
    {
        return new self(sprintf('Progress must be between 0 and 100, got %d.', $value));
    }
}

