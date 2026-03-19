<?php

namespace App\Domain\User\ValueObject;

final readonly class TariffDelay
{
    public function __construct(
        private int $value,
    ) {
        if ($this->value < 0) {
            throw new \DomainException('Tariff delay must be greater than or equal to 0.');
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

