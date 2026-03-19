<?php

namespace App\Domain\User\ValueObject;

final readonly class TariffTitle
{
    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new \DomainException('Tariff title cannot be empty.');
        }

        if (mb_strlen($trimmed) > 255) {
            throw new \DomainException('Tariff title must be less than 255 characters long.');
        }

        $this->value = $trimmed;
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

