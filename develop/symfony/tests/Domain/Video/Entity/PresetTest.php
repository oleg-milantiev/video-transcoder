<?php

declare(strict_types=1);

namespace App\Tests\Domain\Video\Entity;

use App\Domain\Video\Entity\Preset;
use App\Domain\Video\ValueObject\AudioCodec;
use App\Domain\Video\ValueObject\Format;
use App\Domain\Video\ValueObject\PresetBitrate;
use App\Domain\Video\ValueObject\PresetTitle;
use App\Domain\Video\ValueObject\VideoCodec;
use PHPUnit\Framework\TestCase;

/**
 * Tests Preset entity — create/constructor, label(), changeOutput(), bitrate.
 */
final class PresetTest extends TestCase
{
    /** create() создаёт пресет без id; все поля доступны через геттеры. */
    public function testCreateInitializesPresetWithoutId(): void
    {
        $preset = Preset::create(
            new PresetTitle("Test Preset"),
            new VideoCodec("h264"),
            new AudioCodec("aac"),
            new Format("mp4"),
        );

        $this->assertNull($preset->id());
        $this->assertSame("h264/aac/mp4", $preset->label());
        $this->assertSame("h264", $preset->videoCodec()->value());
        $this->assertSame("aac", $preset->audioCodec()->value());
        $this->assertSame("mp4", $preset->format()->value());
        $this->assertInstanceOf(PresetBitrate::class, $preset->bitrate());
    }

    /** changeOutput() обновляет кодек и формат. */
    public function testChangeOutputUpdatesFormat(): void
    {
        $preset = new Preset(
            new PresetTitle("Test Preset"),
            new VideoCodec("h264"),
            new AudioCodec("aac"),
            new Format("mp4"),
        );

        $preset->changeOutput(
            new VideoCodec("h265"),
            new AudioCodec("opus"),
            new Format("webm"),
        );

        $this->assertSame('h265', $preset->videoCodec()->value());
        $this->assertSame('opus', $preset->audioCodec()->value());
        $this->assertSame('webm', $preset->format()->value());
        $this->assertSame('h265/opus/webm', $preset->label());
    }

    public function testCreateWithCustomBitrate(): void
    {
        $bitrate = new PresetBitrate([720 => 5.0, 1080 => 8.0]);
        $preset = Preset::create(
            new PresetTitle("Test Preset"),
            new VideoCodec("h264"),
            new AudioCodec("aac"),
            new Format("mp4"),
            $bitrate,
        );

        $this->assertSame($bitrate, $preset->bitrate());
        $this->assertSame([720 => 5.0, 1080 => 8.0], $preset->bitrate()->value());
    }

    public function testChangeBitrateUpdatesPreset(): void
    {
        $preset = Preset::create(
            new PresetTitle("Test Preset"),
            new VideoCodec("h264"),
            new AudioCodec("aac"),
            new Format("mp4"),
        );

        $newBitrate = new PresetBitrate([480 => 2.5, 720 => 5.0]);
        $preset->changeBitrate($newBitrate);

        $this->assertSame($newBitrate, $preset->bitrate());
        $this->assertSame([480 => 2.5, 720 => 5.0], $preset->bitrate()->value());
    }

    public function testConstructorSetsDefaultBitrateWhenNull(): void
    {
        $preset = new Preset(
            new PresetTitle("Test Preset"),
            new VideoCodec("h264"),
            new AudioCodec("aac"),
            new Format("mp4"),
            null,
            null,
        );

        $this->assertFalse($preset->bitrate()->isEmpty());
        $this->assertNotNull($preset->bitrate()->bitrateForHeight(720));
    }
}
