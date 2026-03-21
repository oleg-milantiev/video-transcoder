<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Ffmpeg;

use App\Infrastructure\Ffmpeg\ProcessRunnerInterface;
use App\Infrastructure\Ffmpeg\VideoPreviewGenerator;
use PHPUnit\Framework\TestCase;

class VideoPreviewGeneratorTest extends TestCase
{
    public function testBuildCommandProducesExpectedArguments(): void
    {
        $command = VideoPreviewGenerator::buildCommand('/tmp/input.mp4', '/tmp/output.jpg', 1.5);

        $this->assertSame([
            'ffmpeg',
            '-y',
            '-ss', '1.5',
            '-i', '/tmp/input.mp4',
            '-frames:v', '1',
            '-q:v', '2',
            '/tmp/output.jpg',
        ], $command);
    }

    public function testGenerateDelegatesToRunnerWithBuiltCommand(): void
    {
        $runner = $this->createMock(ProcessRunnerInterface::class);
        $runner->expects($this->once())
            ->method('mustRun')
            ->with([
                'ffmpeg',
                '-y',
                '-ss', '1',
                '-i', '/tmp/in.mp4',
                '-frames:v', '1',
                '-q:v', '2',
                '/tmp/out.jpg',
            ]);

        $generator = new VideoPreviewGenerator($runner);
        $generator->generate('/tmp/in.mp4', '/tmp/out.jpg', 1.0);
    }
}

