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
}
