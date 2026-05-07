<?php

declare(strict_types=1);

namespace Tests\Application\CommandHandler\Video;

use App\Application\Command\Video\ExtractVideoMetadata;
use App\Application\Command\Video\VideoUploaded;
use App\Application\CommandHandler\Video\VideoUploadedHandler;
use App\Application\Event\VideoUploadedFail;
use App\Application\Factory\FlashNotificationFactory;
use App\Application\Logging\LogServiceInterface;
use App\Application\Service\Storage\StorageRealtimeNotifier;
use App\Application\Service\Video\VideoRealtimeNotifier;
use App\Application\Service\Mercure\FlashRealtimeNotifier;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Entity\Tariff;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\TariffStorageGb;
use App\Domain\User\ValueObject\TariffStorageHour;
use App\Domain\User\ValueObject\TariffVideoSize;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\StorageRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoTitle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class VideoUploadedHandlerTest extends TestCase
{
    private function makeVideo(Uuid $userId, bool $loading = true): Video
    {
        return Video::reconstitute(
            title: new VideoTitle('video'),
            extension: new FileExtension('mp4'),
            userId: $userId,
            meta: [],
            dates: VideoDates::create(),
            id: Uuid::generate(),
            loading: $loading,
        );
    }

    private function makeHandler(
        MessageBusInterface $commandBus,
        MessageBusInterface $eventBus,
        VideoRepositoryInterface $videoRepository,
        StorageRepositoryInterface $storageRepository,
        UserRepositoryInterface $userRepository,
        VideoRealtimeNotifier $notifier,
        FlashRealtimeNotifier $flashRealtimeNotifier,
        LogServiceInterface $logService,
        StorageInterface $storage,
        StorageRealtimeNotifier $storageNotifier,
    ): VideoUploadedHandler {
        return new VideoUploadedHandler(
            $commandBus,
            $eventBus,
            $videoRepository,
            $storageRepository,
            $userRepository,
            $notifier,
            $flashRealtimeNotifier,
            $logService,
            $storage,
            new FlashNotificationFactory(),
            $this->createStub(TaskRepositoryInterface::class),
            $storageNotifier,
        );
    }

    /** После успешной загрузки sourceKey записывается в meta, video->markLoaded(), диспатчится ExtractVideoMetadata. */
    public function testStoresSourceKeyAndDispatchesExtractMetadata(): void
    {
        $userId = Uuid::generate();
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test video content');

        $video = $this->makeVideo($userId);
        $command = new VideoUploaded($video, $tempFile, 'video.mp4');

        $commandBus = new class implements MessageBusInterface {
            public array $dispatched = [];

            public function dispatch($message, array $stamps = []): Envelope
            {
                $this->dispatched[] = $message;
                return new Envelope($message);
            }
        };
        $eventBus = new class implements MessageBusInterface {
            public function dispatch($message, array $stamps = []): Envelope { return new Envelope($message); }
        };

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->once())
            ->method('save')
            ->willReturnCallback(fn(Video $v): Video => $v);

        $storage = $this->createMock(StorageInterface::class);
        $storage->expects($this->once())->method('putFromPath')->willReturn('source/user/video.mp4');
        $storage->method('sourceKey')->willReturn('source/user/video.mp4');

        $storageRepository = $this->createStub(StorageRepositoryInterface::class);
        $storageRepository->method('getUsedStorageSize')->willReturn(1000);

        $userWithTariff = $this->createStub(User::class);
        $tariff = $this->createStub(Tariff::class);
        $tariff->method('videoSize')->willReturn(new TariffVideoSize(1000.0));
        $tariff->method('storageGb')->willReturn(new TariffStorageGb(5));
        $tariff->method('storageHour')->willReturn(new TariffStorageHour(24));
        $userWithTariff->method('id')->willReturn($userId);
        $userWithTariff->method('tariff')->willReturn($tariff);

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($userWithTariff);

        $notifier = new VideoRealtimeNotifier($commandBus, $storage, $this->createStub(TaskRepositoryInterface::class), $userRepository);
        $flashRealtimeNotifier = new FlashRealtimeNotifier($commandBus);
        $logService = $this->createStub(LogServiceInterface::class);

        $handler = $this->makeHandler(
            $commandBus, $eventBus, $videoRepository, $storageRepository, $userRepository,
            $notifier, $flashRealtimeNotifier, $logService, $storage,
            $this->createStub(StorageRealtimeNotifier::class),
        );

        $handler->__invoke($command);

        $extractCmds = array_values(array_filter(
            $commandBus->dispatched,
            static fn(object $m): bool => $m instanceof ExtractVideoMetadata,
        ));

        $this->assertCount(1, $extractCmds);
        $this->assertSame('source/user/video.mp4', $extractCmds[0]->video()->meta()['sourceKey'] ?? null);
        $this->assertFalse($extractCmds[0]->video()->isLoading());
        $this->cleanupTempFile($tempFile);
    }

    /** При ошибке (user not found) диспатчится VideoUploadedFail с правильными userId и videoId. */
    public function testUserNotFoundDispatchesVideoUploadedFail(): void
    {
        $userId = Uuid::generate();
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test video content');

        $video = $this->makeVideo($userId);
        $command = new VideoUploaded($video, $tempFile, 'video.mp4');

        $commandBus = new class implements MessageBusInterface {
            public function dispatch($message, array $stamps = []): Envelope
            {
                return new Envelope($message);
            }
        };
        $eventBus = new class implements MessageBusInterface {
            public array $dispatched = [];
            public function dispatch($message, array $stamps = []): Envelope
            {
                $this->dispatched[] = $message;
                return new Envelope($message);
            }
        };

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn(null);

        $storage = $this->createStub(StorageInterface::class);
        $notifier = new VideoRealtimeNotifier($commandBus, $storage, $this->createStub(TaskRepositoryInterface::class), $userRepository);

        $handler = $this->makeHandler(
            $commandBus, $eventBus, $this->createStub(VideoRepositoryInterface::class),
            $this->createStub(StorageRepositoryInterface::class), $userRepository,
            $notifier, new FlashRealtimeNotifier($commandBus),
            $this->createStub(LogServiceInterface::class), $storage,
            $this->createStub(StorageRealtimeNotifier::class),
        );

        $handler->__invoke($command);

        $found = false;
        foreach ($eventBus->dispatched as $evt) {
            if ($evt instanceof VideoUploadedFail && str_contains($evt->error, 'User not found')) {
                $this->assertSame($userId->toRfc4122(), $evt->userId);
                $this->assertSame($video->id()->toRfc4122(), $evt->videoId);
                $found = true;
            }
        }
        $this->assertTrue($found, 'VideoUploadedFail with "User not found" was not dispatched');
        $this->cleanupTempFile($tempFile);
    }

    /** При отсутствии тарифа диспатчится VideoUploadedFail. */
    public function testTariffNotFoundDispatchesVideoUploadedFail(): void
    {
        $userId = Uuid::generate();
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test video content');

        $video = $this->makeVideo($userId);
        $command = new VideoUploaded($video, $tempFile, 'video.mp4');

        $commandBus = new class implements MessageBusInterface {
            public function dispatch($message, array $stamps = []): Envelope
            {
                return new Envelope($message);
            }
        };
        $eventBus = new class implements MessageBusInterface {
            public array $dispatched = [];
            public function dispatch($message, array $stamps = []): Envelope
            {
                $this->dispatched[] = $message;
                return new Envelope($message);
            }
        };

        $userWithoutTariff = $this->createStub(User::class);
        $userWithoutTariff->method('tariff')->willReturn(null);
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($userWithoutTariff);

        $storage = $this->createStub(StorageInterface::class);
        $notifier = new VideoRealtimeNotifier($commandBus, $storage, $this->createStub(TaskRepositoryInterface::class), $userRepository);

        $handler = $this->makeHandler(
            $commandBus, $eventBus, $this->createStub(VideoRepositoryInterface::class),
            $this->createStub(StorageRepositoryInterface::class), $userRepository,
            $notifier, new FlashRealtimeNotifier($commandBus),
            $this->createStub(LogServiceInterface::class), $storage,
            $this->createStub(StorageRealtimeNotifier::class),
        );

        $handler->__invoke($command);

        $found = false;
        foreach ($eventBus->dispatched as $evt) {
            if ($evt instanceof VideoUploadedFail && str_contains($evt->error, 'Tariff not found')) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'VideoUploadedFail with "Tariff not found" was not dispatched');
        $this->cleanupTempFile($tempFile);
    }

    /** Файл не найден — диспатчится VideoUploadedFail. */
    public function testMissingFileDispatchesVideoUploadedFail(): void
    {
        $userId = Uuid::generate();

        $video = $this->makeVideo($userId);
        $command = new VideoUploaded($video, '/nonexistent/path/video.mp4', 'video.mp4');

        $commandBus = new class implements MessageBusInterface {
            public function dispatch($message, array $stamps = []): Envelope
            {
                return new Envelope($message);
            }
        };
        $eventBus = new class implements MessageBusInterface {
            public array $dispatched = [];
            public function dispatch($message, array $stamps = []): Envelope
            {
                $this->dispatched[] = $message;
                return new Envelope($message);
            }
        };

        $userWithTariff = $this->createStub(User::class);
        $tariff = $this->createStub(Tariff::class);
        $tariff->method('videoSize')->willReturn(new TariffVideoSize(1000.0));
        $tariff->method('storageGb')->willReturn(new TariffStorageGb(5));
        $tariff->method('storageHour')->willReturn(new TariffStorageHour(24));
        $userWithTariff->method('id')->willReturn($userId);
        $userWithTariff->method('tariff')->willReturn($tariff);
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($userWithTariff);

        $storage = $this->createStub(StorageInterface::class);
        $notifier = new VideoRealtimeNotifier($commandBus, $storage, $this->createStub(TaskRepositoryInterface::class), $userRepository);

        $handler = $this->makeHandler(
            $commandBus, $eventBus, $this->createStub(VideoRepositoryInterface::class),
            $this->createStub(StorageRepositoryInterface::class), $userRepository,
            $notifier, new FlashRealtimeNotifier($commandBus),
            $this->createStub(LogServiceInterface::class), $storage,
            $this->createStub(StorageRealtimeNotifier::class),
        );

        $handler->__invoke($command);

        $found = false;
        foreach ($eventBus->dispatched as $evt) {
            if ($evt instanceof VideoUploadedFail && str_contains($evt->error, 'Cannot determine file size')) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'VideoUploadedFail with file not found error was not dispatched');
    }

    /** Размер файла превышает лимит тарифа — диспатчится VideoUploadedFail. */
    public function testFileSizeExceedsTariffLimitDispatchesVideoUploadedFail(): void
    {
        $userId = Uuid::generate();
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        $handle = fopen($tempFile, 'cb');
        self::assertIsResource($handle);
        ftruncate($handle, 100 * 1024 * 1024);
        fclose($handle);

        $video = $this->makeVideo($userId);
        $command = new VideoUploaded($video, $tempFile, 'video.mp4');

        $commandBus = $this->createStub(MessageBusInterface::class);
        $commandBus->method('dispatch')->willReturnCallback(static fn(object $m) => new Envelope($m));

        $eventBus = new class implements MessageBusInterface {
            public array $dispatched = [];
            public function dispatch($message, array $stamps = []): Envelope
            {
                $this->dispatched[] = $message;
                return new Envelope($message);
            }
        };

        $userWithTariff = $this->createStub(User::class);
        $tariff = $this->createStub(Tariff::class);
        $tariff->method('videoSize')->willReturn(new TariffVideoSize(50.0));
        $tariff->method('storageGb')->willReturn(new TariffStorageGb(5));
        $tariff->method('storageHour')->willReturn(new TariffStorageHour(24));
        $userWithTariff->method('id')->willReturn($userId);
        $userWithTariff->method('tariff')->willReturn($tariff);

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->exactly(2))->method('findById')->with($userId)->willReturn($userWithTariff);

        $storage = $this->createStub(StorageInterface::class);
        $storageRepository = $this->createStub(StorageRepositoryInterface::class);
        $storageRepository->method('getUsedStorageSize')->willReturn(0);
        $notifier = new VideoRealtimeNotifier($commandBus, $storage, $this->createStub(TaskRepositoryInterface::class), $userRepository);

        $handler = $this->makeHandler(
            $commandBus, $eventBus, $this->createStub(VideoRepositoryInterface::class),
            $storageRepository, $userRepository,
            $notifier, new FlashRealtimeNotifier($commandBus),
            $this->createStub(LogServiceInterface::class), $storage,
            $this->createStub(StorageRealtimeNotifier::class),
        );

        $handler->__invoke($command);

        $found = false;
        foreach ($eventBus->dispatched as $evt) {
            if ($evt instanceof VideoUploadedFail && str_contains($evt->error, 'exceeds your tariff limit')) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'VideoUploadedFail with size limit error was not dispatched');
        $this->cleanupTempFile($tempFile);
    }

    /** Успешная загрузка вызывает storageNotifier->notifyStorageUpdated(). */
    public function testSuccessfulUploadNotifiesStorage(): void
    {
        $userId = Uuid::generate();
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test video content');

        $video = $this->makeVideo($userId);
        $command = new VideoUploaded($video, $tempFile, 'video.mp4');

        $commandBus = $this->createStub(MessageBusInterface::class);
        $commandBus->method('dispatch')->willReturnCallback(static fn($m) => new Envelope($m));
        $eventBus = $this->createStub(MessageBusInterface::class);
        $eventBus->method('dispatch')->willReturnCallback(static fn($m) => new Envelope($m));

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('save')->willReturnCallback(fn(Video $v): Video => $v);

        $storage = $this->createStub(StorageInterface::class);
        $storage->method('putFromPath')->willReturn('source/key.mp4');
        $storage->method('sourceKey')->willReturn('source/key.mp4');

        $userWithTariff = $this->createStub(User::class);
        $tariff = $this->createStub(Tariff::class);
        $tariff->method('videoSize')->willReturn(new TariffVideoSize(1000.0));
        $tariff->method('storageGb')->willReturn(new TariffStorageGb(5));
        $tariff->method('storageHour')->willReturn(new TariffStorageHour(24));
        $userWithTariff->method('id')->willReturn($userId);
        $userWithTariff->method('tariff')->willReturn($tariff);
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($userWithTariff);

        $storageRepository = $this->createStub(StorageRepositoryInterface::class);
        $storageRepository->method('getUsedStorageSize')->willReturn(0);

        $notifier = new VideoRealtimeNotifier($commandBus, $storage, $this->createStub(TaskRepositoryInterface::class), $userRepository);

        $storageNotifier = $this->createMock(StorageRealtimeNotifier::class);
        $storageNotifier->expects($this->once())->method('notifyStorageUpdated')->with($userId);

        $handler = $this->makeHandler(
            $commandBus, $eventBus, $videoRepository, $storageRepository, $userRepository,
            $notifier, new FlashRealtimeNotifier($commandBus),
            $this->createStub(LogServiceInterface::class), $storage,
            $storageNotifier,
        );

        $handler->__invoke($command);
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

