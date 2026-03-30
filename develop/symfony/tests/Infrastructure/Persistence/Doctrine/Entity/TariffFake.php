<?php

namespace App\tests\Infrastructure\Persistence\Doctrine\Entity;

use App\Infrastructure\Persistence\Doctrine\User\TariffEntity;

final class TariffFake
{
    public static function create(): TariffEntity
    {
        $tariff = new TariffEntity();
        $tariff->title = 'Fake';
        $tariff->instance = 2;
        $tariff->delay = 3600;
        $tariff->storageGb = 1;
        $tariff->storageHour = 24;
        $tariff->videoDuration = 1800;
        $tariff->videoSize = 100;
        $tariff->maxWidth = 1920;
        $tariff->maxHeight = 1280;

        return $tariff;
    }
}
