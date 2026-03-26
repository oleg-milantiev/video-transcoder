<?php

namespace App\Infrastructure\Persistence\Doctrine\User;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Entity\Tariff;
use App\Domain\User\ValueObject\TariffDelay;
use App\Domain\User\ValueObject\TariffInstance;
use App\Domain\User\ValueObject\TariffTitle;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

class TariffMapper
{
    public static function toDomain(TariffEntity $entity): Tariff
    {
        return new Tariff(
            title: new TariffTitle($entity->title),
            delay: new TariffDelay($entity->delay),
            instance: new TariffInstance($entity->instance),
            id: $entity->id ? Uuid::fromString($entity->id->toRfc4122()) : null,
        );
    }

    public static function toDoctrine(Tariff $tariff): TariffEntity
    {
        $entity = new TariffEntity();
        if ($tariff->id() !== null) {
            $entity->id = SymfonyUuid::fromString($tariff->id()->toRfc4122());
        }
        $entity->title = $tariff->title()->value();
        $entity->delay = $tariff->delay()->value();
        $entity->instance = $tariff->instance()->value();

        return $entity;
    }
}
