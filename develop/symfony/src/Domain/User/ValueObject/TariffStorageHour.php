<?php

namespace App\Domain\User\ValueObject;

final readonly class TariffStorageHour
{
    public function __construct(
        private int $value,
    ) {
        if ($this->value < 1) {
            throw new \DomainException('Tariff storage hour must be at least 1 hour.');
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
