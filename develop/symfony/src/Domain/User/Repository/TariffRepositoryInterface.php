<?php

namespace App\Domain\User\Repository;

use App\Domain\User\Entity\Tariff;

interface TariffRepositoryInterface
{
    public function save(Tariff $tariff): void;
    public function findById(int $id): ?Tariff;
    public function findAll(): array;
    public function delete(Tariff $tariff): void;
}
