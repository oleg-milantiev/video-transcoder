<?php

namespace App\Infrastructure\Persistence\Doctrine\User;

use App\Domain\User\Entity\Tarif;

class TarifMapper
{
    public static function toDomain(TarifEntity $entity): Tarif
    {
        return new Tarif(
            title: $entity->title,
            timeDelay: $entity->timeDelay,
            id: $entity->id
        );
    }

    public static function toDoctrine(Tarif $tarif): TarifEntity
    {
        $entity = new TarifEntity();
        $entity->id = $tarif->id();
        $entity->title = $tarif->title();
        $entity->timeDelay = $tarif->timeDelay();

        return $entity;
    }
}
