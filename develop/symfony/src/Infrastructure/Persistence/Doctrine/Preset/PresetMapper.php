<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Preset;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Preset;
use App\Domain\Video\ValueObject\AudioCodec;
use App\Domain\Video\ValueObject\Format;
use App\Domain\Video\ValueObject\VideoCodec;
use App\Infrastructure\Persistence\Doctrine\User\TariffMapper;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

class PresetMapper
{
    public static function toDomain(PresetEntity $entity): Preset
    {
        return new Preset(
            videoCodec: new VideoCodec($entity->videoCodec),
            audioCodec: new AudioCodec($entity->audioCodec),
            format: new Format($entity->format),
            id: Uuid::fromString($entity->id->toRfc4122()),
        );
    }
}
