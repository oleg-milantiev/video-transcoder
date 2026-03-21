<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Ffmpeg;

use App\Infrastructure\Ffmpeg\ProcessRunnerInterface;
use App\Infrastructure\Ffmpeg\VideoMetadataExtractor;
use PHPUnit\Framework\TestCase;

class VideoMetadataExtractorTest extends TestCase
{
    public function testBuildCommandProducesExpectedArguments(): void
    {
        $command = VideoMetadataExtractor::buildCommand('/tmp/input.mp4');

        $this->assertSame([
            'ffprobe',
            '-v', 'quiet',
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
            '/tmp/input.mp4',
        ], $command);
    }

    public function testExtractParsesMetadataFromRunnerOutput(): void
    {
        $json = json_encode([
            'format' => [
                'duration' => '12.345',
                'bit_rate' => '550000',
                'format_name' => 'mov,mp4,m4a,3gp,3g2,mj2',
                'size' => '100500',
            ],
            'streams' => [
                ['codec_type' => 'audio', 'codec_name' => 'aac'],
                [
                    'codec_type' => 'video',
                    'width' => 1920,
                    'height' => 1080,
                    'codec_name' => 'h264',
                    'avg_frame_rate' => '30/1',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $runner = $this->createMock(ProcessRunnerInterface::class);
        $runner->expects($this->once())
            ->method('mustRunAndGetOutput')
            ->with(VideoMetadataExtractor::buildCommand('/tmp/input.mp4'))
            ->willReturn($json);

        $extractor = new VideoMetadataExtractor($runner);
        $metadata = $extractor->extract('/tmp/input.mp4');

        $this->assertSame(12.345, $metadata['duration']);
        $this->assertSame(550000, $metadata['bitrate']);
        $this->assertSame('mov,mp4,m4a,3gp,3g2,mj2', $metadata['format']);
        $this->assertSame(100500, $metadata['size']);
        $this->assertSame(1920, $metadata['width']);
        $this->assertSame(1080, $metadata['height']);
        $this->assertSame('h264', $metadata['codec']);
        $this->assertSame('30/1', $metadata['frame_rate']);
    }

    public function testExtractThrowsWhenOutputIsInvalidJson(): void
    {
        $runner = $this->createStub(ProcessRunnerInterface::class);
        $runner->method('mustRunAndGetOutput')->willReturn('not-json');

        $extractor = new VideoMetadataExtractor($runner);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to parse ffprobe output.');

        $extractor->extract('/tmp/input.mp4');
    }
}

