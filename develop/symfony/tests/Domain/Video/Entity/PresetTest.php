<?php

declare(strict_types=1);

namespace App\Tests\Domain\Video\Entity;

use App\Domain\Video\Entity\Preset;
use App\Domain\Video\ValueObject\AudioCodec;
use App\Domain\Video\ValueObject\Format;
use App\Domain\Video\ValueObject\VideoCodec;
use PHPUnit\Framework\TestCase;

/**
 * Tests Preset entity — create/constructor, label(), changeOutput().
 * title removed; label() is computed from videoCodec/audioCodec/format.
 */
final class PresetTest extends TestCase
{
    /** create() создаёт пресет без id; все поля доступны через геттеры. */
    public function testCreateInitializesPresetWithoutId(): void
    {
        $preset = Preset::create(
            new VideoCodec('h264'),
            new AudioCodec('aac'),
            new Format('mp4'),
        );

        $this->assertNull($preset->id());
        $this->assertSame('h264/aac/mp4', $preset->label());
        $this->assertSame('h264', $preset->videoCodec()->value());
        $this->assertSame('aac', $preset->audioCodec()->value());
        $this->assertSame('mp4', $preset->format()->value());
    }

    /** changeOutput() обновляет кодек и формат. */
    public function testChangeOutputUpdatesFormat(): void
    {
        $preset = new Preset(
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
        $this->assertSame('h265/opus/webm', $preset->label());
    }
}
