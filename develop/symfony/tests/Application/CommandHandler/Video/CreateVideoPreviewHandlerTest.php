<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler\Video;

use App\Application\Command\Video\CreateVideoPreview;
use App\Application\CommandHandler\Video\CreateVideoPreviewHandler;
use App\Application\Event\CreateVideoPreviewFail;
use App\Application\Event\CreateVideoPreviewStart;
use App\Application\Event\CreateVideoPreviewSuccess;
use App\Application\Command\Mercure\PublishMercureMessage;
use App\Application\Service\Video\VideoRealtimeNotifier;
use App\Domain\Video\ValueObject\VideoDates;
use Psr\Log\LogLevel;
use App\Application\Logging\LogServiceInterface;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Exception\VideoPreviewGenerationFailed;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoTitle;
use App\Infrastructure\Ffmpeg\ProcessRunnerInterface;
use App\Infrastructure\Ffmpeg\VideoPreviewGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Domain\Shared\ValueObject\Uuid;

class CreateVideoPreviewHandlerTest extends TestCase
{
    public function testGeneratesPreviewAndDispatchesSuccessEvent(): void
    {
        $video = $this->createVideo(3.2);

        $storage = $this->createMock(StorageInterface::class);
        $storage->expects($this->once())
            ->method('sourceKey')
            ->with($video)
            ->willReturn('video.mp4');
        $storage->expects($this->exactly(2))
            ->method('previewKey')
            ->with($video)
            ->willReturn('video.jpg');
        $storage->expects($this->once())
            ->method('publicUrl')
            ->with('video.jpg')
            ->willReturn('/uploads/video.jpg');
        $storage->expects($this->once())
            ->method('localPathForRead')
            ->with('video.mp4')
            ->willReturn('/tmp/video.mp4');
        $storage->expects($this->once())
            ->method('localPathForWrite')
            ->with('video.jpg')
            ->willReturn('/tmp/video.jpg');
        $storage->expects($this->once())
            ->method('publishLocalFile')
            ->with('/tmp/video.jpg', 'video.jpg');

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
        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())
            ->method('log')
            ->with('video', $video->id(), LogLevel::INFO, 'Preview Created');

        $processRunner = $this->createMock(ProcessRunnerInterface::class);
        $processRunner->expects($this->once())
            ->method('mustRun')
            ->with(VideoPreviewGenerator::buildCommand('/tmp/video.mp4', '/tmp/video.jpg', 1.0));

        $generator = new VideoPreviewGenerator($processRunner);

        $notifierCommandBus = $this->createMock(MessageBusInterface::class);
        $notifierCommandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(PublishMercureMessage::class))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));
        $notifier = new VideoRealtimeNotifier($notifierCommandBus, $storage);

        $handler = new CreateVideoPreviewHandler($storage, $eventBus, $logService, $videoRepository, $notifier, $generator);
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
        $storage->method('sourceKey')->willReturn('video.mp4');
        $storage->method('previewKey')->willReturn('video.jpg');
        $storage->method('localPathForRead')->willReturn('/tmp/video.mp4');
        $storage->method('localPathForWrite')->willReturn('/tmp/video.jpg');

        $eventBus = $this->createMock(MessageBusInterface::class);
        $events = [];
        $eventBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$events): Envelope {
                $events[] = $message::class;

                return new Envelope($message);
            });

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())
            ->method('log')
            ->with('video', $video->id(), LogLevel::ERROR, 'Error Create Preview', ['message' => 'ffmpeg boom']);

        $processRunner = $this->createStub(ProcessRunnerInterface::class);
        $processRunner->method('mustRun')->willThrowException(new \RuntimeException('ffmpeg boom'));

        $generator = new VideoPreviewGenerator($processRunner);

        $notifierCommandBus = $this->createMock(MessageBusInterface::class);
        $notifierCommandBus->expects($this->never())->method('dispatch');
        $notifier = new VideoRealtimeNotifier($notifierCommandBus, $storage);

        $handler = new CreateVideoPreviewHandler($storage, $eventBus, $logService, $videoRepository, $notifier, $generator);

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
        return Video::reconstitute(
            title: new VideoTitle('Clip'),
            extension: new FileExtension('mp4'),
            userId: Uuid::fromString('123e4567-e89b-42d3-a456-426614174105'),
            meta: ['duration' => $duration],
            dates: VideoDates::create(),
            id: Uuid::fromString('123e4567-e89b-42d3-a456-426614174151'),
        );
    }
}



