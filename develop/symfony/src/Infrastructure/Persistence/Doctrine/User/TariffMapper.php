<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\User;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Entity\Tariff;
use App\Domain\User\ValueObject\TariffDelay;
use App\Domain\User\ValueObject\TariffInstance;
use App\Domain\User\ValueObject\TariffMaxHeight;
use App\Domain\User\ValueObject\TariffMaxWidth;
use App\Domain\User\ValueObject\TariffStorageGb;
use App\Domain\User\ValueObject\TariffStorageHour;
use App\Domain\User\ValueObject\TariffTitle;
use App\Domain\User\ValueObject\TariffVideoDuration;
use App\Domain\User\ValueObject\TariffVideoSize;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

class TariffMapper
{
    public static function toDomain(TariffEntity $entity): Tariff
    {
        return new Tariff(
            title: new TariffTitle($entity->title),
            delay: new TariffDelay($entity->delay),
            instance: new TariffInstance($entity->instance),
            videoDuration: new TariffVideoDuration($entity->videoDuration),
            videoSize: new TariffVideoSize($entity->videoSize),
            maxWidth: new TariffMaxWidth($entity->maxWidth),
            maxHeight: new TariffMaxHeight($entity->maxHeight),
            storageGb: new TariffStorageGb($entity->storageGb),
            storageHour: new TariffStorageHour($entity->storageHour),
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
        $entity->videoDuration = $tariff->videoDuration()->value();
        $entity->videoSize = $tariff->videoSize()->value();
        $entity->maxWidth = $tariff->maxWidth()->value();
        $entity->maxHeight = $tariff->maxHeight()->value();
        $entity->storageGb = $tariff->storageGb()->value();
        $entity->storageHour = $tariff->storageHour()->value();

        return $entity;
    }
}
