<?php
declare(strict_types=1);

namespace App\Domain\Video\Exception;

final class InvalidPresetName extends \DomainException
{
    public static function fromValue(string $name): self
    {
        return new self(sprintf('Invalid Preset Name: %s', $name));
    }
}
