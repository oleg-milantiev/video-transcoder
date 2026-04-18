<?php

declare(strict_types=1);

namespace App\Tests\Domain\Video\Entity;

use App\Domain\Video\Entity\Preset;
use App\Domain\Video\ValueObject\AudioCodec;
use App\Domain\Video\ValueObject\Format;
use App\Domain\Video\ValueObject\PresetTitle;
use App\Domain\Video\ValueObject\VideoCodec;
use PHPUnit\Framework\TestCase;
use App\Domain\Shared\ValueObject\Uuid;

/**
 * Tests Preset entity — create/constructor, rename, changeOutput.
 * width, height, bitrate are now stored in task.meta, not in Preset.
 */
final class PresetTest extends TestCase
{
    /** create() создаёт пресет без id; все поля доступны через геттеры. */
    public function testCreateInitializesPresetWithoutId(): void
    {
        $preset = Preset::create(
            new PresetTitle('HD 1080p'),
            new VideoCodec('h264'),
            new AudioCodec('aac'),
            new Format('mp4'),
        );

        $this->assertNull($preset->id());
        $this->assertSame('HD 1080p', $preset->title()->value());
        $this->assertSame('h264', $preset->videoCodec()->value());
        $this->assertSame('aac', $preset->audioCodec()->value());
        $this->assertSame('mp4', $preset->format()->value());
    }

    /** rename() обновляет заголовок, id остаётся неизменным. */
    public function testRenameUpdatesTitle(): void
    {
        $preset = new Preset(
            new PresetTitle('Initial'),
            new VideoCodec('h264'),
            new AudioCodec('aac'),
            new Format('mp4'),
            Uuid::fromString('11111111-1111-4111-8111-111111111111'),
        );

        $preset->rename(new PresetTitle('Updated'));

        $this->assertSame('11111111-1111-4111-8111-111111111111', $preset->id()->toRfc4122());
        $this->assertSame('Updated', $preset->title()->value());
    }

    /** changeOutput() обновляет кодек и формат. */
    public function testChangeOutputUpdatesFormat(): void
    {
        $preset = new Preset(
            new PresetTitle('Mobile'),
            new VideoCodec('h264'),
            new AudioCodec('aac'),
            new Format('mp4'),
        );

        $preset->changeOutput(
            new VideoCodec('h265'),
            new AudioCodec('opus'),
            new Format('webm'),
        );

        $this->assertSame('h265', $preset->videoCodec()->value());
        $this->assertSame('opus', $preset->audioCodec()->value());
        $this->assertSame('webm', $preset->format()->value());
    }
}
