<?php

declare(strict_types=1);

namespace Tests\Application\CommandHandler\Video;

use App\Application\Command\Video\CreateVideo;
use App\Application\CommandHandler\Video\CreateVideoHandler;
use App\Application\Event\CreateVideoFail;
use App\Application\Factory\FlashNotificationFactory;
use App\Application\Factory\VideoFactory;
use App\Application\Service\Video\VideoRealtimeNotifier;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoTitle;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Application\Logging\LogServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use TusPhp\File as TusFile;
use Symfony\Component\Uid\UuidV4 as Uuid;

class CreateVideoHandlerTest extends TestCase
{
    public function testStorageUploadExceptionDispatchesCreateVideoFail(): void
    {
        $userId = new Uuid();

        // Mock TusPhp/File
        $file = $this->createStub(TusFile::class);
        $file->method('getName')->willReturn('video.mp4');
        $file->method('getFilePath')->willReturn('/tmp/video.mp4');
        $file->method('details')->willReturn(['metadata' => ['originalName' => 'video.mp4']]);

        $command = new CreateVideo($file, $userId);

        // Prepare a video instance returned by factory and repository
        $videoToCreate = Video::create(
            new VideoTitle('video.mp4'),
            new FileExtension('mp4'),
            $userId,
        );

        $savedVideo = Video::reconstitute(
            new VideoTitle('video.mp4'),
            new FileExtension('mp4'),
            $userId,
            [],
            VideoDates::create(),
            new Uuid(),
        );

        $commandBus = $this->createStub(MessageBusInterface::class);

        // Simple spy for event bus to record dispatched messages (CreateVideoStart + CreateVideoFail)
        $eventBus = new class implements MessageBusInterface {
            public array $dispatched = [];

            public function dispatch($message, array $stamps = []): Envelope
            {
                $this->dispatched[] = $message;
                return new Envelope($message);
            }
        };

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('save')->willReturn($savedVideo);

        // instantiate real notifier with a mocked command bus to avoid mocking final class
        $notifier = new VideoRealtimeNotifier($commandBus);
        $logService = $this->createStub(LogServiceInterface::class);

        $storage = $this->createStub(StorageInterface::class);
        $storage->method('upload')->willThrowException(new \Exception('IO error'));

        // use real VideoFactory and FlashNotificationFactory (they are simple final classes)
        $videoFactory = new VideoFactory();
        $flashFactory = new FlashNotificationFactory();

        $handler = new CreateVideoHandler(
            $commandBus,
            $eventBus,
            $videoRepository,
            $notifier,
            $logService,
            $storage,
            $videoFactory,
            $flashFactory,
        );

        // Invoke handler - should catch exception and dispatch CreateVideoFail
        $handler->__invoke($command);

        // Assert that among dispatched events there is a CreateVideoFail with expected props
        $found = false;
        foreach ($eventBus->dispatched as $evt) {
            if ($evt instanceof CreateVideoFail) {
                $this->assertSame($userId->toRfc4122(), $evt->userId);
                $this->assertSame('video.mp4', $evt->filename);
                $found = true;
            }
        }

        $this->assertTrue($found, 'CreateVideoFail event was not dispatched');
    }
}
