<?php

namespace App\Domain\Video\Exception;

final class IncompatibleVideoFormat extends \DomainException
{
    public static function fromValue(string $name): self
    {
        return new self(sprintf('Incompatible Video Format: %s', $name));
    }
}
