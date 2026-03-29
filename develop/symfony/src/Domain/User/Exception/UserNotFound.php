<?php

namespace App\Domain\User\Exception;

final class UserNotFound extends \DomainException
{
    public static function byId(string $userId): self
    {
        return new self(sprintf('User not found: %s', $userId));
    }
}
