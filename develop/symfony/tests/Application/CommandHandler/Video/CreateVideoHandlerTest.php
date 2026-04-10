<?php

declare(strict_types=1);

namespace Tests\Application\CommandHandler\Video;

use App\Application\Command\Video\CreateVideo;
use App\Application\Command\Video\ExtractVideoMetadata;
use App\Application\CommandHandler\Video\CreateVideoHandler;
use App\Application\Event\CreateVideoFail;
use App\Application\Factory\FlashNotificationFactory;
use App\Application\Factory\VideoFactory;
use App\Application\Service\Video\VideoRealtimeNotifier;
use App\Domain\User\Entity\Tariff;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\TariffStorageGb;
use App\Domain\User\ValueObject\TariffVideoSize;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoTitle;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Application\Logging\LogServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use TusPhp\File as TusFile;
use App\Domain\Shared\ValueObject\Uuid;

class CreateVideoHandlerTest extends TestCase
{
    public function testStoresSourceKeyInVideoMetaAfterUpload(): void
    {
        $userId = Uuid::generate();

        // Create a real temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test video content');

        $file = $this->createStub(TusFile::class);
        $file->method('getName')->willReturn('video.mp4');
        $file->method('getFilePath')->willReturn($tempFile);
        $file->method('details')->willReturn(['metadata' => ['originalName' => 'video.mp4']]);

        $command = new CreateVideo($file, $userId);

        $createdVideo = Video::create(
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
            Uuid::generate(),
        );

        $commandBus = new class implements MessageBusInterface {
            public array $dispatched = [];

            public function dispatch($message, array $stamps = []): Envelope
            {
                $this->dispatched[] = $message;

                return new Envelope($message);
            }
        };

        $eventBus = new class implements MessageBusInterface {
            public function dispatch($message, array $stamps = []): Envelope
            {
                return new Envelope($message);
            }
        };

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $saveCall = 0;
        $videoRepository->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(static function (Video $video) use ($savedVideo, &$saveCall): Video {
                $saveCall++;

                return $saveCall === 1 ? $savedVideo : $video;
            });
        $videoRepository->method('getStorageSize')->willReturn(1000);

        $storage = $this->createMock(StorageInterface::class);
        $storage->expects($this->once())
            ->method('putFromPath')
            ->willReturn('source/user/video.mp4');
        $storage->method('sourceKey')->willReturn('source/user/video.mp4');

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('getStorageSize')->willReturn(0);

        $notifier = new VideoRealtimeNotifier($commandBus, $storage, $taskRepository);
        $logService = $this->createStub(LogServiceInterface::class);

        // Create user with tariff that allows the file size
        $userWithTariff = $this->createStub(User::class);
        $tariff = $this->createStub(Tariff::class);
        $tariff->method('videoSize')->willReturn(new TariffVideoSize(1000.0)); // 1000 MB limit
        $tariff->method('storageGb')->willReturn(new TariffStorageGb(5));
        $userWithTariff->method('id')->willReturn($userId);
        $userWithTariff->method('tariff')->willReturn($tariff);

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($userWithTariff);

        $handler = new CreateVideoHandler(
            $commandBus,
            $eventBus,
            $videoRepository,
            $userRepository,
            $notifier,
            $logService,
            $storage,
            new VideoFactory(),
            new FlashNotificationFactory(),
            $taskRepository,
        );

        $handler->__invoke($command);

        $extractCommands = array_values(array_filter(
            $commandBus->dispatched,
            static fn (object $message): bool => $message instanceof ExtractVideoMetadata,
        ));

        $this->assertCount(1, $extractCommands);
        $this->assertSame('source/user/video.mp4', $extractCommands[0]->video()->meta()['sourceKey'] ?? null);

        // Cleanup
        $this->cleanupTempFile($tempFile);
    }

    public function testStorageUploadExceptionDispatchesCreateVideoFail(): void
    {
        $userId = Uuid::generate();

        // Create a real temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test video content');

        // Mock TusPhp/File
        $file = $this->createStub(TusFile::class);
        $file->method('getName')->willReturn('video.mp4');
        $file->method('getFilePath')->willReturn($tempFile);
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
            Uuid::generate(),
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
        $storage = $this->createStub(StorageInterface::class);
        $notifier = new VideoRealtimeNotifier($commandBus, $storage, $this->createStub(TaskRepositoryInterface::class));
        $logService = $this->createStub(LogServiceInterface::class);

        $storage->method('putFromPath')->willThrowException(new \Exception('IO error'));

        // use real VideoFactory and FlashNotificationFactory (they are simple final classes)
        $videoFactory = new VideoFactory();
        $flashFactory = new FlashNotificationFactory();

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn(null);

        $handler = new CreateVideoHandler(
            $commandBus,
            $eventBus,
            $videoRepository,
            $userRepository,
            $notifier,
            $logService,
            $storage,
            $videoFactory,
            $flashFactory,
            $this->createStub(TaskRepositoryInterface::class),
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

        // Cleanup
        $this->cleanupTempFile($tempFile);
    }

    public function testFileSizeExceedingTariffLimitDispatchesCreateVideoFail(): void
    {
        $userId = Uuid::generate();

        // Create a temp file with known size - use 100 MB file to test against 50 MB limit
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        $handle = fopen($tempFile, 'cb');
        self::assertIsResource($handle);
        ftruncate($handle, 100 * 1024 * 1024);
        fclose($handle);

        $file = $this->createStub(TusFile::class);
        $file->method('getName')->willReturn('video.mp4');
        $file->method('getFilePath')->willReturn($tempFile);
        $file->method('details')->willReturn(['metadata' => ['originalName' => 'video.mp4']]);

        $command = new CreateVideo($file, $userId);

        $commandBus = $this->createStub(MessageBusInterface::class);

        $eventBus = new class implements MessageBusInterface {
            public array $dispatched = [];

            public function dispatch($message, array $stamps = []): Envelope
            {
                $this->dispatched[] = $message;
                return new Envelope($message);
            }
        };

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);

        // Use stubs (not mocks) for User and Tariff since no expectations are configured
        $userWithTariff = $this->createStub(User::class);
        $tariff = $this->createStub(Tariff::class);
        // Use real TariffVideoSize since it's final
        $tariff->method('videoSize')->willReturn(new TariffVideoSize(50.0));
        $userWithTariff->method('tariff')->willReturn($tariff);

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($userWithTariff);

        $storage = $this->createStub(StorageInterface::class);
        $notifier = new VideoRealtimeNotifier($commandBus, $storage, $this->createStub(TaskRepositoryInterface::class));
        $logService = $this->createStub(LogServiceInterface::class);

        $handler = new CreateVideoHandler(
            $commandBus,
            $eventBus,
            $videoRepository,
            $userRepository,
            $notifier,
            $logService,
            $storage,
            new VideoFactory(),
            new FlashNotificationFactory(),
            $this->createStub(TaskRepositoryInterface::class),
        );

        $handler->__invoke($command);

        // Assert that CreateVideoFail was dispatched with size limit error message
        $found = false;
        foreach ($eventBus->dispatched as $evt) {
            if ($evt instanceof CreateVideoFail && strpos($evt->error, 'exceeds your tariff limit') !== false) {
                $found = true;
            }
        }

        $this->assertTrue($found, 'CreateVideoFail event with size limit error was not dispatched');

        // Cleanup
        $this->cleanupTempFile($tempFile);
    }

    public function testMissingFileDispatchesCreateVideoFail(): void
    {
        $userId = Uuid::generate();

        $file = $this->createStub(TusFile::class);
        $file->method('getName')->willReturn('video.mp4');
        $file->method('getFilePath')->willReturn('/nonexistent/path/video.mp4');
        $file->method('details')->willReturn(['metadata' => ['originalName' => 'video.mp4']]);

        $command = new CreateVideo($file, $userId);

        $commandBus = $this->createStub(MessageBusInterface::class);

        $eventBus = new class implements MessageBusInterface {
            public array $dispatched = [];

            public function dispatch($message, array $stamps = []): Envelope
            {
                $this->dispatched[] = $message;
                return new Envelope($message);
            }
        };

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $storage = $this->createStub(StorageInterface::class);
        $notifier = new VideoRealtimeNotifier($commandBus, $storage, $this->createStub(TaskRepositoryInterface::class));
        $logService = $this->createStub(LogServiceInterface::class);

        $handler = new CreateVideoHandler(
            $commandBus,
            $eventBus,
            $videoRepository,
            $userRepository,
            $notifier,
            $logService,
            $storage,
            new VideoFactory(),
            new FlashNotificationFactory(),
            $this->createStub(TaskRepositoryInterface::class),
        );

        $handler->__invoke($command);

        // Assert that CreateVideoFail was dispatched with file not found error
        $found = false;
        foreach ($eventBus->dispatched as $evt) {
            if ($evt instanceof CreateVideoFail && strpos($evt->error, 'Cannot determine file size') !== false) {
                $found = true;
            }
        }

        $this->assertTrue($found, 'CreateVideoFail event with file not found error was not dispatched');
    }

    public function testUserNotFoundDispatchesCreateVideoFail(): void
    {
        $userId = Uuid::generate();

        // Create a real temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test video content');

        $file = $this->createStub(TusFile::class);
        $file->method('getName')->willReturn('video.mp4');
        $file->method('getFilePath')->willReturn($tempFile);
        $file->method('details')->willReturn(['metadata' => ['originalName' => 'video.mp4']]);

        $command = new CreateVideo($file, $userId);

        $commandBus = $this->createStub(MessageBusInterface::class);

        $eventBus = new class implements MessageBusInterface {
            public array $dispatched = [];

            public function dispatch($message, array $stamps = []): Envelope
            {
                $this->dispatched[] = $message;
                return new Envelope($message);
            }
        };

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn(null);

        $storage = $this->createStub(StorageInterface::class);
        $notifier = new VideoRealtimeNotifier($commandBus, $storage, $this->createStub(TaskRepositoryInterface::class));
        $logService = $this->createStub(LogServiceInterface::class);

        $handler = new CreateVideoHandler(
            $commandBus,
            $eventBus,
            $videoRepository,
            $userRepository,
            $notifier,
            $logService,
            $storage,
            new VideoFactory(),
            new FlashNotificationFactory(),
            $this->createStub(TaskRepositoryInterface::class),
        );

        $handler->__invoke($command);

        // Assert that CreateVideoFail was dispatched with user not found error
        $found = false;
        foreach ($eventBus->dispatched as $evt) {
            if ($evt instanceof CreateVideoFail && strpos($evt->error, 'User not found') !== false) {
                $found = true;
            }
        }

        $this->assertTrue($found, 'CreateVideoFail event with user not found error was not dispatched');

        // Cleanup
        $this->cleanupTempFile($tempFile);
    }

    public function testTariffNotFoundDispatchesCreateVideoFail(): void
    {
        $userId = Uuid::generate();

        // Create a real temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test video content');

        $file = $this->createStub(TusFile::class);
        $file->method('getName')->willReturn('video.mp4');
        $file->method('getFilePath')->willReturn($tempFile);
        $file->method('details')->willReturn(['metadata' => ['originalName' => 'video.mp4']]);

        $command = new CreateVideo($file, $userId);

        $commandBus = $this->createStub(MessageBusInterface::class);

        $eventBus = new class implements MessageBusInterface {
            public array $dispatched = [];

            public function dispatch($message, array $stamps = []): Envelope
            {
                $this->dispatched[] = $message;
                return new Envelope($message);
            }
        };

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);

        // Create stub user without tariff
        $userWithoutTariff = $this->createStub(User::class);
        $userWithoutTariff->method('tariff')->willReturn(null);

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($userWithoutTariff);

        $storage = $this->createStub(StorageInterface::class);
        $notifier = new VideoRealtimeNotifier($commandBus, $storage, $this->createStub(TaskRepositoryInterface::class));
        $logService = $this->createStub(LogServiceInterface::class);

        $handler = new CreateVideoHandler(
            $commandBus,
            $eventBus,
            $videoRepository,
            $userRepository,
            $notifier,
            $logService,
            $storage,
            new VideoFactory(),
            new FlashNotificationFactory(),
            $this->createStub(TaskRepositoryInterface::class),
        );

        $handler->__invoke($command);

        // Assert that CreateVideoFail was dispatched with tariff not found error
        $found = false;
        foreach ($eventBus->dispatched as $evt) {
            if ($evt instanceof CreateVideoFail && strpos($evt->error, 'Tariff not found') !== false) {
                $found = true;
            }
        }

        $this->assertTrue($found, 'CreateVideoFail event with tariff not found error was not dispatched');

        // Cleanup
        $this->cleanupTempFile($tempFile);

    }

    private function cleanupTempFile(string|false $tempFile): void
    {
        if (!is_string($tempFile) || $tempFile === '' || !file_exists($tempFile)) {
            return;
        }

        unlink($tempFile);
    }
}
