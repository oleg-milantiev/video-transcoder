<?php
declare(strict_types=1);

namespace App\Domain\User\ValueObject;

final readonly class UserEmail
{
    private const int MAX_LENGTH = 180;

    private string $value;

    public function __construct(string $value)
    {
        $normalized = mb_strtolower(trim($value));

        if ($normalized === '') {
            throw new \DomainException('User email cannot be empty.');
        }

        if (mb_strlen($normalized) > self::MAX_LENGTH) {
            throw new \DomainException('User email must be less than or equal to 180 characters long.');
        }

        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new \DomainException('User email has invalid format.');
        }

        $this->value = $normalized;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
