<?php
declare(strict_types=1);

namespace App\Domain\User\ValueObject;

final readonly class PasswordHash
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new \DomainException('Password hash cannot be empty.');
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
}
