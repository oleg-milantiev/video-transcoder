<?php

declare(strict_types=1);

namespace App\Tests\Unit\VideoRealtime;

use App\Application\Command\Mercure\PublishMercureMessage;
use App\Application\DTO\MercureMessageDTO;
use App\Application\Service\Video\VideoRealtimeNotifier;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoTitle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class VideoRealtimeNotifierTest extends TestCase
{
    public function testNotifyVideoUpdatedDispatchesPublishCommandWithDtoPayload(): void
    {
        $video = Video::reconstitute(
            title: new VideoTitle('Original title'),
            extension: new FileExtension('mp4'),
            userId: Uuid::generate(),
            meta: ['preview' => true],
            dates: VideoDates::create(),
            id: Uuid::generate(),
        );

        $storage = $this->createMock(StorageInterface::class);
        $storage->expects($this->once())
            ->method('previewKey')
            ->with($video)
            ->willReturn('preview/key.jpg');
        $storage->expects($this->once())
            ->method('publicUrl')
            ->with('preview/key.jpg')
            ->willReturn('/uploads/preview.jpg');

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $command): Envelope {
                if (!$command instanceof PublishMercureMessage) {
                    throw new \RuntimeException('Expected PublishMercureMessage');
                }

                $message = $command->message;
                if (!$message instanceof MercureMessageDTO) {
                    throw new \RuntimeException('Expected MercureMessageDTO');
                }

                TestCase::assertSame('updated', $message->action);
                TestCase::assertSame('video', $message->entity);
                TestCase::assertArrayHasKey('videoId', $message->payload);
                TestCase::assertArrayHasKey('deleted', $message->payload);
                TestCase::assertSame('/uploads/preview.jpg', $message->payload['poster']);
                TestCase::assertSame('Overridden title', $message->payload['title']);
                TestCase::assertSame('upload complete', $message->payload['note']);

                return new Envelope($command);
            });

        $notifier = new VideoRealtimeNotifier($commandBus, $storage, $this->createStub(TaskRepositoryInterface::class));
        $notifier->notifyVideoUpdated($video, 'updated', [
            'title' => 'Overridden title',
            'note' => 'upload complete',
        ]);
    }

    public function testNotifyVideoUpdatedReturnsEarlyWhenVideoIdIsMissing(): void
    {
        $video = Video::create(
            new VideoTitle('Draft video'),
            new FileExtension('mp4'),
            Uuid::generate(),
            ['preview' => true],
        );

        $storage = $this->createMock(StorageInterface::class);
        $storage->expects($this->never())->method('previewKey');
        $storage->expects($this->never())->method('publicUrl');

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->never())->method('dispatch');

        $notifier = new VideoRealtimeNotifier($commandBus, $storage, $this->createStub(TaskRepositoryInterface::class));
        $notifier->notifyVideoUpdated($video, 'uploaded');
    }
}
