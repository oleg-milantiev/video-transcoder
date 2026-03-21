<?php

namespace App\Infrastructure\Persistence\Doctrine\Preset;

use App\Domain\Video\Entity\Preset;
use App\Domain\Video\ValueObject\Bitrate;
use App\Domain\Video\ValueObject\Codec;
use App\Domain\Video\ValueObject\PresetTitle;
use App\Domain\Video\ValueObject\Resolution;

class PresetMapper
{
    public static function toDomain(PresetEntity $entity): Preset
    {
        return new Preset(
            title: new PresetTitle($entity->title),
            resolution: new Resolution($entity->width, $entity->height),
            codec: new Codec($entity->codec),
            bitrate: new Bitrate($entity->bitrate),
            id: $entity->id,
        );
    }

    public static function toDoctrine(Preset $preset): PresetEntity
    {
        $entity = new PresetEntity();
        if ($preset->id() !== null) {
            $entity->id = $preset->id();
        }
        $entity->title = $preset->title()->value();
        $entity->width = $preset->resolution()->width();
        $entity->height = $preset->resolution()->height();
        $entity->codec = $preset->codec()->value();
        $entity->bitrate = $preset->bitrate()->value();

        return $entity;
    }
}
