<?php
declare(strict_types=1);

namespace App\Domain\User\Exception;

use DomainException;

final class UserNotFound extends DomainException
{
    public static function byId(string $userId): self
    {
        return new self(sprintf('User not found: %s', $userId));
    }
}
