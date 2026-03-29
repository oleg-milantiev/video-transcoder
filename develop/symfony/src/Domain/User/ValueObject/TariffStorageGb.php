<?php

namespace App\Domain\User\ValueObject;

final readonly class TariffStorageGb
{
    public function __construct(
        private float $value,
    ) {
        if ($this->value <= 0) {
            throw new \DomainException('Tariff storage must be greater than 0 gigabytes.');
        }
    }

    public function value(): float
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
