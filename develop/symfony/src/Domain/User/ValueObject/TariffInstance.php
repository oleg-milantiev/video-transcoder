<?php

namespace App\Domain\User\ValueObject;

final readonly class TariffInstance
{
    public function __construct(
        private int $value,
    ) {
        if ($this->value < 1) {
            throw new \DomainException('Tariff instance must be greater than or equal to 1.');
        }
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}

