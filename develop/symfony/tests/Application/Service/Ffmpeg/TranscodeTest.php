<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Ffmpeg;

use App\Application\DTO\TranscodeStartContextDTO;
use App\Domain\Video\Entity\Preset;
use App\Domain\Video\ValueObject\VideoCodec;
use App\Domain\Video\ValueObject\AudioCodec;
use App\Domain\Video\ValueObject\Format;
use App\Domain\Video\ValueObject\PresetTitle;
use App\Infrastructure\Ffmpeg\Transcode;
use App\Tests\Domain\Entity\TaskFake;
use App\Tests\Domain\Entity\VideoFake;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use App\Domain\Shared\ValueObject\Uuid;

class TranscodeTest extends TestCase
{
    public function testBuildCommandProducesExpectedArguments(): void
    {
        $preset = $this->createPreset('HD 1080p', 'h264');
        $task = TaskFake::create();
        $task->updateMeta(['width' => 1920, 'height' => 1080, 'bitrate' => 5.0]);

        $command = Transcode::buildCommand(new TranscodeStartContextDTO(
            task: $task,
            video: VideoFake::create(),
            preset: $preset,
            relativeOutputPath: 'output.mp4',
            absoluteOutputPath: '/tmp/output.mp4',
            inputPath: '/tmp/input.mp4',
            timeStart: 0.0));

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
        $preset = $this->createPreset('Preset ' . $codec, $codec);
        $task = TaskFake::create();
        $task->updateMeta(['width' => 1280, 'height' => 720, 'bitrate' => 5.0]);

        $command = Transcode::buildCommand(new TranscodeStartContextDTO(
            task: $task,
            video: VideoFake::create(),
            preset: $preset,
            relativeOutputPath: 'output.mp4',
            absoluteOutputPath: 'out',
            inputPath: 'in',
            timeStart: 0.0));

        $index = array_search('-c:v', $command, true);
        $this->assertNotFalse($index);
        $this->assertSame($ffmpegCodec, $command[$index + 1]);
    }

    #[DataProvider('audioCodecProvider')]
    public function testBuildCommandMapsAudioCodecVariants(string $audioCodec, string $ffmpegAudioCodec): void
    {
        $preset = $this->createPresetWithAudio('Preset with ' . $audioCodec, 'h264', $audioCodec);
        $task = TaskFake::create();
        $task->updateMeta(['width' => 1280, 'height' => 720, 'bitrate' => 5.0]);

        $command = Transcode::buildCommand(new TranscodeStartContextDTO(
            task: $task,
            video: VideoFake::create(),
            preset: $preset,
            relativeOutputPath: 'output.mp4',
            absoluteOutputPath: 'out',
            inputPath: 'in',
            timeStart: 0.0));

        $index = array_search('-c:a', $command, true);
        $this->assertNotFalse($index);
        $this->assertSame($ffmpegAudioCodec, $command[$index + 1]);
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

    public static function audioCodecProvider(): array
    {
        return [
            ['aac', 'aac'],
            ['opus', 'libopus'],
        ];
    }

    public function testBuildCommandAppliesBitrateFloor(): void
    {
        $preset = $this->createPreset('Low bitrate', 'h264');
        $task = TaskFake::create();
        $task->updateMeta(['width' => 640, 'height' => 360, 'bitrate' => 0.05]);

        $command = Transcode::buildCommand(new TranscodeStartContextDTO(
            task: $task,
            video: VideoFake::create(),
            preset: $preset,
            relativeOutputPath: 'output.mp4',
            absoluteOutputPath: 'out',
            inputPath: 'in',
            timeStart: 0.0));

        $index = array_search('-b:v', $command, true);
        $this->assertNotFalse($index);
        $this->assertSame('100k', $command[$index + 1]);
    }

    private function createPreset(string $title, string $codec): Preset
    {
        return new Preset(
            new PresetTitle($title),
            new VideoCodec($codec),
            new AudioCodec('aac'),
            new Format('mp4'),
            id: Uuid::fromString('11111111-1111-4111-8111-111111111111'),
        );
    }

    private function createPresetWithAudio(string $title, string $codec, string $audioCodec): Preset
    {
        return new Preset(
            new PresetTitle($title),
            new VideoCodec($codec),
            new AudioCodec($audioCodec),
            new Format('mp4'),
            id: Uuid::fromString('11111111-1111-4111-8111-111111111111'),
        );
    }
}
