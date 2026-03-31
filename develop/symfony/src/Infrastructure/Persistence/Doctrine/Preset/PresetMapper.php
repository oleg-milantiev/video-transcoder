<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Preset;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Preset;
use App\Domain\Video\ValueObject\Bitrate;
use App\Domain\Video\ValueObject\Codec;
use App\Domain\Video\ValueObject\PresetTitle;
use App\Domain\Video\ValueObject\Resolution;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

class PresetMapper
{
    public static function toDomain(PresetEntity $entity): Preset
    {
        return new Preset(
            title: new PresetTitle($entity->title),
            resolution: new Resolution($entity->width, $entity->height),
            codec: new Codec($entity->codec),
            bitrate: new Bitrate($entity->bitrate),
            id: Uuid::fromString($entity->id->toRfc4122()),
        );
    }

    public static function toDoctrine(Preset $preset): PresetEntity
    {
        $entity = new PresetEntity();
        if ($preset->id() !== null) {
            $entity->id = SymfonyUuid::fromString($preset->id()->toRfc4122());
        }
        $entity->title = $preset->title()->value();
        $entity->width = $preset->resolution()->width();
        $entity->height = $preset->resolution()->height();
        $entity->codec = $preset->codec()->value();
        $entity->bitrate = $preset->bitrate()->value();

        return $entity;
    }
}
