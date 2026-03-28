<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Video;

use App\Application\Service\Video\VideoRealtimeNotifier;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoTitle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class VideoRealtimeNotifierTest extends TestCase
{
    public function testNotifyVideoUpdatedDispatchesMessage(): void
    {
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $userId = Uuid::fromString('22222222-2222-4222-8222-222222222222');

        $video = Video::reconstitute(
            new VideoTitle('Test Video'),
            new FileExtension('mp4'),
            $userId,
            ['preview' => true],
            VideoDates::create(),
            $videoId,
        );

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $message): Envelope {
                return new Envelope($message);
            });

        $storage = $this->createMock(StorageInterface::class);
        $storage->expects($this->once())
            ->method('previewKey')
            ->with($video)
            ->willReturn('previews/' . $videoId->toRfc4122() . '.jpg');
        $storage->expects($this->once())
            ->method('publicUrl')
            ->willReturn('/uploads/preview.jpg');

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())
            ->method('findByVideoId')
            ->with($videoId)
            ->willReturn([]);

        $notifier = new VideoRealtimeNotifier($commandBus, $storage, $taskRepository);
        $notifier->notifyVideoUpdated($video, 'updated', ['extra' => 'data']);
    }

    public function testNotifyVideoUpdatedWithoutPoster(): void
    {
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $userId = Uuid::fromString('22222222-2222-4222-8222-222222222222');

        $video = Video::reconstitute(
            new VideoTitle('No Preview Video'),
            new FileExtension('mp4'),
            $userId,
            [],  // no preview
            VideoDates::create(),
            $videoId,
        );

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $message): Envelope {
                return new Envelope($message);
            });

        $storage = $this->createStub(StorageInterface::class);
        // Don't expect publicUrl to be called since there's no preview

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findByVideoId')->willReturn([]);

        $notifier = new VideoRealtimeNotifier($commandBus, $storage, $taskRepository);
        $notifier->notifyVideoUpdated($video);
    }

    public function testNotifyVideoUpdatedWithDeletedVideo(): void
    {
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $userId = Uuid::fromString('22222222-2222-4222-8222-222222222222');

        $video = Video::reconstitute(
            new VideoTitle('Deleted Video'),
            new FileExtension('mp4'),
            $userId,
            [],
            VideoDates::create(),
            $videoId,
            true,  // deleted
        );

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $message): Envelope {
                return new Envelope($message);
            });

        $storage = $this->createStub(StorageInterface::class);

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())
            ->method('findByVideoId')
            ->with($videoId)
            ->willReturn([]);

        $notifier = new VideoRealtimeNotifier($commandBus, $storage, $taskRepository);
        $notifier->notifyVideoUpdated($video, 'deleted');
    }
}
