<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Ffmpeg;

use App\Application\Service\Ffmpeg\Transcode;
use App\Domain\Video\Entity\Preset;
use App\Domain\Video\ValueObject\Bitrate;
use App\Domain\Video\ValueObject\Codec;
use App\Domain\Video\ValueObject\PresetTitle;
use App\Domain\Video\ValueObject\Resolution;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TranscodeTest extends TestCase
{
    public function testBuildCommandProducesExpectedArguments(): void
    {
        $preset = $this->createPreset('HD 1080p', 'h264', 5.0, 1920, 1080);

        $command = Transcode::buildCommand('/tmp/input.mp4', '/tmp/output.mp4', $preset);

        $expected = [
            'ffmpeg',
            '-y',
            '-i', '/tmp/input.mp4',
            '-vf', 'scale=1920:1080',
            '-c:v', 'libx264',
            '-b:v', '5000k',
            '-preset', 'medium',
            '-movflags', '+faststart',
            '-c:a', 'aac',
            '-b:a', '128k',
            '-progress', 'pipe:2',
            '-nostats',
            '/tmp/output.mp4',
        ];

        $this->assertSame($expected, $command);
    }

    #[DataProvider('codecProvider')]
    public function testBuildCommandMapsCodecVariants(string $codec, string $ffmpegCodec): void
    {
        $preset = $this->createPreset('Preset '.$codec, $codec, 5.0, 1280, 720);

        $command = Transcode::buildCommand('in', 'out', $preset);

        $index = array_search('-c:v', $command, true);
        $this->assertNotFalse($index);
        $this->assertSame($ffmpegCodec, $command[$index + 1]);
    }

    public static function codecProvider(): array
    {
        return [
            ['h264', 'libx264'],
            ['h265', 'libx265'],
            ['vp9', 'libvpx-vp9'],
            ['av1', 'libaom-av1'],
        ];
    }

    public function testBuildCommandAppliesBitrateFloor(): void
    {
        $preset = $this->createPreset('Low bitrate', 'h264', 0.05, 640, 360);

        $command = Transcode::buildCommand('in', 'out', $preset);

        $index = array_search('-b:v', $command, true);
        $this->assertNotFalse($index);
        $this->assertSame('100k', $command[$index + 1]);
    }

    private function createPreset(string $title, string $codec, float $bitrate, int $width, int $height): Preset
    {
        return new Preset(
            new PresetTitle($title),
            new Resolution($width, $height),
            new Codec($codec),
            new Bitrate($bitrate),
            id: 1,
        );
    }
}
