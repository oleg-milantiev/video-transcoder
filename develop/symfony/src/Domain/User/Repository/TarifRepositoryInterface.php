<?php

namespace App\Domain\User\Repository;

use App\Domain\User\Entity\Tarif;

interface TarifRepositoryInterface
{
    public function save(Tarif $tarif): void;
    public function findById(int $id): ?Tarif;
    public function findAll(): array;
    public function delete(Tarif $tarif): void;
}
