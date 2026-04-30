<?php
declare(strict_types=1);

namespace App\Domain\Video\Repository;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Entity\Tariff;
use App\Domain\Video\Entity\Preset;

interface PresetRepositoryInterface
{
    public function findById(Uuid $id): ?Preset;

    /**
     * @return Preset[]
     */
    public function findByTariff(Tariff $tariff): array;

    /**
     * Returns all presets linked to the tariff of the given user.
     * Results are ordered by title ascending.
     *
     * @return Preset[]
     */
    public function findForUser(Uuid $userId): array;
}
