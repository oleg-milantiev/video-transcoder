<?php
declare(strict_types=1);

namespace App\Domain\Video\Exception;

use DomainException;

final class IncompatibleVideoFormat extends DomainException
{
    public static function fromValue(string $name): self
    {
        return new self(sprintf('Incompatible Video Format: %s', $name));
    }
}
