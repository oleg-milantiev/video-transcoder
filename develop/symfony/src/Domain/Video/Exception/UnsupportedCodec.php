<?php

namespace App\Domain\Video\Exception;

final class UnsupportedCodec extends \DomainException
{
    public static function fromValue(string $codec): self
    {
        return new self(sprintf('Unsupported codec: %s', $codec));
    }
}
