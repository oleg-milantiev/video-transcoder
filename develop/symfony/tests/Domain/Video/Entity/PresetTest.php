<?php

declare(strict_types=1);

namespace App\Tests\Domain\Video\Entity;

use App\Domain\Video\Entity\Preset;
use App\Domain\Video\ValueObject\AudioCodec;
use App\Domain\Video\ValueObject\Bitrate;
use App\Domain\Video\ValueObject\Format;
use App\Domain\Video\ValueObject\PresetTitle;
use App\Domain\Video\ValueObject\Resolution;
use App\Domain\Video\ValueObject\VideoCodec;
use PHPUnit\Framework\TestCase;
use App\Domain\Shared\ValueObject\Uuid;

/**
 * Tests Preset entity — create/constructor, rename, changeOutput и бизнес-правила совместимости
 * (4K требует bitrate ≥ 8, AV1 требует bitrate ≥ 1).
 */
final class PresetTest extends TestCase
{
    /** create() создаёт пресет без id; все поля доступны через геттеры. */
    public function testCreateInitializesPresetWithoutId(): void
    {
        $preset = Preset::create(
            new PresetTitle('HD 1080p'),
            new Resolution(1920, 1080),
            new VideoCodec('h264'),
            new Bitrate(5.0),
            new AudioCodec('aac'),
            new Format('mp4'),
        );

        $this->assertNull($preset->id());
        $this->assertSame('HD 1080p', $preset->title()->value());
        $this->assertSame(1920, $preset->resolution()->width());
        $this->assertSame(1080, $preset->resolution()->height());
        $this->assertSame('h264', $preset->videoCodec()->value());
        $this->assertSame(5.0, $preset->bitrate()->value());
        $this->assertSame('aac', $preset->audioCodec()->value());
        $this->assertSame('mp4', $preset->format()->value());
    }

    /** rename() обновляет заголовок, id остаётся неизменным. */
    public function testRenameUpdatesTitle(): void
    {
        $preset = new Preset(
            new PresetTitle('Initial'),
            new Resolution(1280, 720),
            new VideoCodec('h264'),
            new Bitrate(3.0),
            new AudioCodec('aac'),
            new Format('mp4'),
            Uuid::fromString('11111111-1111-4111-8111-111111111111'),
        );

        $preset->rename(new PresetTitle('Updated'));

        $this->assertSame('11111111-1111-4111-8111-111111111111', $preset->id()->toRfc4122());
        $this->assertSame('Updated', $preset->title()->value());
    }

    /** changeOutput() обновляет разрешение, кодек и битрейт. */
    public function testChangeOutputUpdatesFormat(): void
    {
        $preset = new Preset(
            new PresetTitle('Mobile'),
            new Resolution(854, 480),
            new VideoCodec('h264'),
            new Bitrate(2.0),
            new AudioCodec('aac'),
            new Format('mp4'),
        );

        $preset->changeOutput(
            new Resolution(2560, 1440),
            new VideoCodec('h265'),
            new Bitrate(9.5),
            new AudioCodec('opus'),
            new Format('webm'),
        );

        $this->assertSame(2560, $preset->resolution()->width());
        $this->assertSame(1440, $preset->resolution()->height());
        $this->assertSame('h265', $preset->videoCodec()->value());
        $this->assertSame(9.5, $preset->bitrate()->value());
        $this->assertSame('opus', $preset->audioCodec()->value());
        $this->assertSame('webm', $preset->format()->value());
    }

    /** 4K-разрешение с bitrate < 8 бросает DomainException (инвариант совместимости). */
    public function testThrowsWhen4kBitrateIsTooLow(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Bitrate is too low for 4K resolution.');

        new Preset(
            new PresetTitle('4K Low'),
            new Resolution(3840, 2160),
            new VideoCodec('h264'),
            new Bitrate(7.9),
            new AudioCodec('aac'),
            new Format('mp4'),
        );
    }

    /** AV1-кодек с bitrate < 1 бросает DomainException (инвариант совместимости). */
    public function testThrowsWhenAv1BitrateIsTooLow(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Bitrate is too low for AV1 preset.');

        new Preset(
            new PresetTitle('AV1 Low'),
            new Resolution(1920, 1080),
            new VideoCodec('av1'),
            new Bitrate(0.9),
            new AudioCodec('opus'),
            new Format('webm'),
        );
    }
}
