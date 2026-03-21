<?php

declare(strict_types=1);

namespace App\Tests\Application\Factory;

use App\Application\Command\Video\CreateVideo;
use App\Application\Factory\VideoFactory;
use App\Domain\Video\ValueObject\VideoStatus;
use PHPUnit\Framework\TestCase;
use TusPhp\File;

final class VideoFactoryTest extends TestCase
{
    public function testFromCreateVideoUsesOriginalNameFromMetadata(): void
    {
        $factory = new VideoFactory();
        $file = $this->mockTusFile(
            name: 'upload-c4f2.mp4',
            details: ['metadata' => ['originalName' => 'My Holiday Clip.mp4']],
        );

        $video = $factory->fromCreateVideo(new CreateVideo($file, 42));

        $this->assertSame('My Holiday Clip.mp4', $video->title()->value());
        $this->assertSame('mp4', $video->extension()->value());
        $this->assertSame(VideoStatus::UPLOADED, $video->status());
        $this->assertSame(42, $video->userId());
    }

    public function testFromCreateVideoFallsBackToUploadedNameWhenMetadataMissing(): void
    {
        $factory = new VideoFactory();
        $file = $this->mockTusFile(
            name: 'server-name.mkv',
            details: [],
        );

        $video = $factory->fromCreateVideo(new CreateVideo($file, 7));

        $this->assertSame('server-name.mkv', $video->title()->value());
        $this->assertSame('mkv', $video->extension()->value());
        $this->assertSame(7, $video->userId());
    }

    private function mockTusFile(string $name, array $details): File
    {
        $file = $this->createStub(File::class);

        $file->method('getName')->willReturn($name);
        $file->method('getFilePath')->willReturn('/tmp/' . $name);
        $file->method('details')->willReturn($details);

        return $file;
    }
}
