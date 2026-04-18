<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Preset;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Preset;
use App\Domain\Video\ValueObject\AudioCodec;
use App\Domain\Video\ValueObject\Format;
use App\Domain\Video\ValueObject\PresetTitle;
use App\Domain\Video\ValueObject\VideoCodec;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

class PresetMapper
{
    public static function toDomain(PresetEntity $entity): Preset
    {
        return new Preset(
            title: new PresetTitle($entity->title),
            videoCodec: new VideoCodec($entity->videoCodec),
            audioCodec: new AudioCodec($entity->audioCodec),
            format: new Format($entity->format),
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
        $entity->videoCodec = $preset->videoCodec()->value();
        $entity->audioCodec = $preset->audioCodec()->value();
        $entity->format = $preset->format()->value();

        return $entity;
    }
}
