<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Video\Entity\Preset;
use App\Infrastructure\Persistence\Doctrine\Preset\PresetEntity;
use App\Infrastructure\Persistence\Doctrine\Preset\PresetMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

final class PresetMapperTest extends TestCase
{
    private PresetEntity $entity;

    protected function setUp(): void
    {
        $this->entity = new PresetEntity();
        $this->entity->id = SymfonyUuid::fromString('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb');
        $this->entity->title = 'HD 720p';
        $this->entity->videoCodec = 'h264';
        $this->entity->audioCodec = 'aac';
        $this->entity->format = 'mp4';
        $this->entity->bitrate = null;
    }

    public function testToDomainMapsAllFields(): void
    {
        $preset = PresetMapper::toDomain($this->entity);

        self::assertInstanceOf(Preset::class, $preset);
        self::assertSame('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', $preset->id()->toRfc4122());
        self::assertSame('HD 720p', $preset->title()->value());
        self::assertSame('h264', $preset->videoCodec()->value());
        self::assertSame('aac', $preset->audioCodec()->value());
        self::assertSame('mp4', $preset->format()->value());
    }

    public function testToDomainWithNullBitrateUsesDefault(): void
    {
        $this->entity->bitrate = null;
        $preset = PresetMapper::toDomain($this->entity);

        self::assertNotNull($preset->bitrate());
    }

    public function testToDomainWithBitrateData(): void
    {
        $this->entity->bitrate = ['720' => 2.5, '1080' => 5.0];
        $preset = PresetMapper::toDomain($this->entity);

        self::assertSame(2.5, $preset->bitrate()->bitrateForHeight(720));
        self::assertSame(5.0, $preset->bitrate()->bitrateForHeight(1080));
    }
}
