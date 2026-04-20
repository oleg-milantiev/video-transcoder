<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Preset;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Preset;
use App\Domain\Video\ValueObject\AudioCodec;
use App\Domain\Video\ValueObject\Format;
use App\Domain\Video\ValueObject\PresetBitrate;
use App\Domain\Video\ValueObject\PresetTitle;
use App\Domain\Video\ValueObject\VideoCodec;

class PresetMapper
{
    public static function toDomain(PresetEntity $entity): Preset
    {
        $bitrate = $entity->bitrate !== null
            ? PresetBitrate::fromRaw($entity->bitrate)
            : PresetBitrate::default();

        return new Preset(
            title: new PresetTitle($entity->title),
            videoCodec: new VideoCodec($entity->videoCodec),
            audioCodec: new AudioCodec($entity->audioCodec),
            format: new Format($entity->format),
            id: Uuid::fromString($entity->id->toRfc4122()),
            bitrate: $bitrate,
        );
    }
}
