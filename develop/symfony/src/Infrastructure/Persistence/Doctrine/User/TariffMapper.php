<?php

namespace App\Infrastructure\Persistence\Doctrine\User;

use App\Domain\User\Entity\Tariff;

class TariffMapper
{
    public static function toDomain(TariffEntity $entity): Tariff
    {
        return new Tariff(
            title: $entity->title,
            delay: $entity->delay,
            instance: $entity->instance,
            id: $entity->id
        );
    }

    public static function toDoctrine(Tariff $tariff): TariffEntity
    {
        $entity = new TariffEntity();
        $entity->id = $tariff->id();
        $entity->title = $tariff->title();
        $entity->delay = $tariff->delay();
        $entity->instance = $tariff->instance();

        return $entity;
    }
}
