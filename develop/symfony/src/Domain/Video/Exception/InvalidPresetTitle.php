<?php
declare(strict_types=1);

namespace App\Domain\Video\Exception;

final class InvalidPresetTitle extends \DomainException
{
    public static function fromValue(string $title): self
    {
        return new self(sprintf('Invalid Preset Title: %s', $title));
    }
}

