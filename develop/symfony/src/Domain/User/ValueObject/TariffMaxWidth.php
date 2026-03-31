<?php
declare(strict_types=1);

namespace App\Domain\User\ValueObject;

final readonly class TariffMaxWidth
{
    public function __construct(
        private int $value,
    ) {
        if ($this->value < 1) {
            throw new \DomainException('Tariff max width must be at least 1 pixel.');
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
