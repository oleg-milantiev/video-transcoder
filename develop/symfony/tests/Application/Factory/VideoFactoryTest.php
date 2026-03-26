<?php

declare(strict_types=1);

namespace App\Tests\Application\Factory;

use App\Application\Command\Video\CreateVideo;
use App\Application\Factory\VideoFactory;
use PHPUnit\Framework\TestCase;
use App\Domain\Shared\ValueObject\Uuid;
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

        $video = $factory->fromCreateVideo(new CreateVideo($file, Uuid::fromString('00000000-0000-4000-8000-000000000042')));

        $this->assertSame('My Holiday Clip', $video->title()->value());
        $this->assertSame('mp4', $video->extension()->value());
        $this->assertSame('00000000-0000-4000-8000-000000000042', $video->userId()->toRfc4122());
    }

    public function testFromCreateVideoFallsBackToUploadedNameWhenMetadataMissing(): void
    {
        $factory = new VideoFactory();
        $file = $this->mockTusFile(
            name: 'server-name.mkv',
            details: [],
        );

        $video = $factory->fromCreateVideo(new CreateVideo($file, Uuid::fromString('00000000-0000-4000-8000-000000000007')));

        $this->assertSame('server-name', $video->title()->value());
        $this->assertSame('mkv', $video->extension()->value());
        $this->assertSame('00000000-0000-4000-8000-000000000007', $video->userId()->toRfc4122());
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
