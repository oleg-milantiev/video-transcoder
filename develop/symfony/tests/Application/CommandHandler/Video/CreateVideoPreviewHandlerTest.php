<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler\Video;

use App\Application\Command\Video\CreateVideoPreview;
use App\Application\CommandHandler\Video\CreateVideoPreviewHandler;
use App\Application\Event\CreateVideoPreviewFail;
use App\Application\Event\CreateVideoPreviewStart;
use App\Application\Event\CreateVideoPreviewSuccess;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Exception\VideoPreviewGenerationFailed;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoStatus;
use App\Domain\Video\ValueObject\VideoTitle;
use App\Infrastructure\Ffmpeg\ProcessRunnerInterface;
use App\Infrastructure\Ffmpeg\VideoPreviewGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\UuidV4;

class CreateVideoPreviewHandlerTest extends TestCase
{
    public function testGeneratesPreviewAndDispatchesSuccessEvent(): void
    {
        $video = $this->createVideo(3.2);

        $storage = $this->createMock(StorageInterface::class);
        $storage->expects($this->once())
            ->method('getAbsolutePath')
            ->with($video->getSrcFilename())
            ->willReturn('/tmp/video.mp4');

        $eventBus = $this->createMock(MessageBusInterface::class);
        $events = [];
        $eventBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$events): Envelope {
                $events[] = $message::class;

                return new Envelope($message);
            });

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (Video $savedVideo): bool {
                return ($savedVideo->meta()['preview'] ?? false) === true;
            }));
        $videoRepository->expects($this->once())
            ->method('log')
            ->with($video->id(), 'info', 'Preview Created');

        $processRunner = $this->createMock(ProcessRunnerInterface::class);
        $processRunner->expects($this->once())
            ->method('mustRun')
            ->with(VideoPreviewGenerator::buildCommand('/tmp/video.mp4', '/tmp/video.jpg', 1.0));

        $generator = new VideoPreviewGenerator($processRunner);

        $handler = new CreateVideoPreviewHandler($storage, $eventBus, $videoRepository, $generator);
        $handler(new CreateVideoPreview($video));

        $this->assertSame([
            CreateVideoPreviewStart::class,
            CreateVideoPreviewSuccess::class,
        ], $events);
    }

    public function testDispatchesFailEventAndThrowsDomainException(): void
    {
        $video = $this->createVideo(0.5);

        $storage = $this->createStub(StorageInterface::class);
        $storage->method('getAbsolutePath')->willReturn('/tmp/video.mp4');

        $eventBus = $this->createMock(MessageBusInterface::class);
        $events = [];
        $eventBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$events): Envelope {
                $events[] = $message::class;

                return new Envelope($message);
            });

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->once())
            ->method('log')
            ->with($video->id(), 'error', 'Error Preview creating: ffmpeg boom');

        $processRunner = $this->createStub(ProcessRunnerInterface::class);
        $processRunner->method('mustRun')->willThrowException(new \RuntimeException('ffmpeg boom'));

        $generator = new VideoPreviewGenerator($processRunner);

        $handler = new CreateVideoPreviewHandler($storage, $eventBus, $videoRepository, $generator);

        $this->expectException(VideoPreviewGenerationFailed::class);

        try {
            $handler(new CreateVideoPreview($video));
        } finally {
            $this->assertSame([
                CreateVideoPreviewStart::class,
                CreateVideoPreviewFail::class,
            ], $events);
        }
    }

    private function createVideo(float $duration): Video
    {
        return Video::create(
            title: new VideoTitle('Clip'),
            extension: new FileExtension('mp4'),
            status: VideoStatus::UPLOADED,
            userId: UuidV4::fromString('123e4567-e89b-42d3-a456-426614174105'),
            meta: ['duration' => $duration],
            id: UuidV4::fromString('123e4567-e89b-42d3-a456-426614174151'),
        );
    }
}



