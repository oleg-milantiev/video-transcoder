<?php

namespace App\Infrastructure\Persistence\Doctrine\Preset;

use App\Domain\Video\Entity\Preset;

class PresetMapper
{
    public static function toDomain(PresetEntity $entity): Preset
    {
        return new Preset(
            name: $entity->name,
            resolution: $entity->resolution,
            codec: $entity->codec,
            bitrate: $entity->bitrate,
            id: $entity->id,
        );
    }

    public static function toDoctrine(Preset $preset): PresetEntity
    {
        $entity = new PresetEntity();
        $entity->id = $preset->id();
        $entity->name = $preset->name();
        $entity->resolution = $preset->resolution();
        $entity->codec = $preset->codec();
        $entity->bitrate = $preset->bitrate();

        return $entity;
    }
}
