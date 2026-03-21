<?php

namespace App\Domain\User\Repository;

use App\Domain\User\Entity\Tariff;
use Symfony\Component\Uid\UuidV4 as Uuid;

interface TariffRepositoryInterface
{
    public function save(Tariff $tariff): void;
    public function findById(Uuid $id): ?Tariff;
    public function findAll(): array;
    public function delete(Tariff $tariff): void;
}
