<?php
declare(strict_types=1);

namespace App\Domain\User\ValueObject;

final readonly class UserCreatedAt
{
    public function __construct(private \DateTimeImmutable $value)
    {
    }

    public function value(): \DateTimeImmutable
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value == $other->value;
    }

    public function __toString(): string
    {
        return $this->value->format(\DateTimeInterface::ATOM);
    }
}
