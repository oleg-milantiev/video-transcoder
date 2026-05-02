<?php

declare(strict_types=1);

namespace Tests\Application\CommandHandler\Video;

use App\Application\Command\Video\VideoUpload;
use App\Application\Command\Video\VideoUploaded;
use App\Application\CommandHandler\Video\VideoUploadHandler;
use App\Application\Event\VideoUploadedFail;
use App\Application\Factory\FlashNotificationFactory;
use App\Application\Logging\LogServiceInterface;
use App\Application\Service\Mercure\FlashRealtimeNotifier;
use App\Application\Service\Video\UrlVideoDownloader;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Entity\Tariff;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\TariffStorageGb;
use App\Domain\Video\DTO\PaginatedResult;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\StorageRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoTitle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class VideoUploadHandlerTest extends TestCase
{
    private function makeVideo(Uuid $userId): Video
    {
        return Video::reconstitute(
            title: new VideoTitle('video'),
            extension: new FileExtension('mp4'),
            userId: $userId,
            meta: [],
            dates: VideoDates::create(),
            id: Uuid::generate(),
            loading: true,
        );
    }

    private function makeHandler(
        MessageBusInterface $commandBus,
        MessageBusInterface $eventBus,
        VideoRepositoryInterface $videoRepository,
        UrlVideoDownloader $urlDownloader,
        ?UserRepositoryInterface $userRepository = null,
        ?StorageRepositoryInterface $storageRepository = null,
    ): VideoUploadHandler {
        if ($userRepository === null) {
            $tariff = $this->createStub(Tariff::class);
            $tariff->method('storageGb')->willReturn(new TariffStorageGb(100.0));
            $user = $this->createStub(User::class);
            $user->method('tariff')->willReturn($tariff);
            $userRepository = $this->createStub(UserRepositoryInterface::class);
            $userRepository->method('findById')->willReturn($user);
        }

        if ($storageRepository === null) {
            $storageRepository = $this->createStub(StorageRepositoryInterface::class);
            $storageRepository->method('getUsedStorageSize')->willReturn(0);
        }

        return new VideoUploadHandler(
            $commandBus,
            $eventBus,
            $videoRepository,
            $userRepository,
            $storageRepository,
            $urlDownloader,
            new FlashRealtimeNotifier($commandBus),
            new FlashNotificationFactory(),
            $this->createStub(LogServiceInterface::class),
        );
    }

    private function makeVideoRepository(array &$savedVideos): VideoRepositoryInterface
    {
        return new class ($savedVideos) implements VideoRepositoryInterface {
            public function __construct(private array &$savedVideos) {}
            public function save(Video $video): Video { $this->savedVideos[] = $video; return $video; }
            public function findById(Uuid $id): ?Video { return null; }
            public function findIdBySession(string $session, Uuid $userId): ?Uuid { return null; }
            public function findVideoPage(Uuid $videoId, Uuid $userId, int $limit): int { return 1; }
            public function getActiveCount(Uuid $userId): int { return 0; }
            public function getTotalCount(Uuid $userId): int { return 0; }
            public function findDeletedVideoForCleanup(): array { return []; }
            public function findAll(Uuid $userId, int $page, int $limit): array { return []; }
            public function count(Uuid $userId): int { return 0; }
            public function findAllPaginated(int $page, int $limit, Uuid $userId): PaginatedResult { return new PaginatedResult([], 0); }
        };
    }

    /** Успешная загрузка: диспатчится VideoUploaded с filePath и filename, видео помечается loading=false. */
    public function testSuccessfulDownloadDispatchesVideoUploaded(): void
    {
        $userId = Uuid::generate();
        $video = $this->makeVideo($userId);
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test video data');

        $dispatched = [];
        $commandBus = new class ($dispatched) implements MessageBusInterface {
            public function __construct(private array &$dispatched) {}
            public function dispatch($message, array $stamps = []): Envelope
            {
                $this->dispatched[] = $message;
                return new Envelope($message);
            }
        };
        $eventBus = new class ($dispatched) implements MessageBusInterface {
            public function __construct(private array &$dispatched) {}
            public function dispatch($message, array $stamps = []): Envelope
            {
                $this->dispatched[] = $message;
                return new Envelope($message);
            }
        };

        $savedVideos = [];
        $videoRepository = $this->makeVideoRepository($savedVideos);

        $urlDownloader = $this->createStub(UrlVideoDownloader::class);
        $urlDownloader->method('headRequest')->willReturn(['content-length' => '15']);
        $urlDownloader->method('download')->willReturn([
            'path' => $tempFile,
            'filename' => 'my_video.mp4',
            'size' => 15,
        ]);

        $handler = $this->makeHandler($commandBus, $eventBus, $videoRepository, $urlDownloader);
        $handler->__invoke(new VideoUpload($video, 'https://example.com/my_video.mp4'));

        // Video must have been saved (once for size, once for markLoaded)
        $this->assertGreaterThanOrEqual(1, count($savedVideos));
        $this->assertFalse($savedVideos[array_key_last($savedVideos)]->isLoading());

        // VideoUploaded must be dispatched with correct filePath and filename
        $uploadedCmds = array_values(array_filter(
            $dispatched,
            static fn(object $m): bool => $m instanceof VideoUploaded,
        ));
        $this->assertCount(1, $uploadedCmds);
        $this->assertSame($tempFile, $uploadedCmds[0]->filePath());
        $this->assertSame('my_video.mp4', $uploadedCmds[0]->filename());

        @unlink($tempFile);
    }

    /** Превышение квоты: видео помечается deleted, диспатчится VideoUploadedFail. */
    public function testStorageQuotaExceededMarksVideoDeleted(): void
    {
        $userId = Uuid::generate();
        $video = $this->makeVideo($userId);

        $dispatched = [];
        $bus = new class ($dispatched) implements MessageBusInterface {
            public function __construct(private array &$dispatched) {}
            public function dispatch($message, array $stamps = []): Envelope
            {
                $this->dispatched[] = $message;
                return new Envelope($message);
            }
        };

        $savedVideos = [];
        $videoRepository = $this->makeVideoRepository($savedVideos);

        // Tariff: 1 GB, used: 1 GB + 1 byte (already full)
        $tariff = $this->createStub(Tariff::class);
        $tariff->method('storageGb')->willReturn(new TariffStorageGb(1.0));
        $user = $this->createStub(User::class);
        $user->method('tariff')->willReturn($tariff);
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($user);

        $storageRepository = $this->createStub(StorageRepositoryInterface::class);
        // After saving video with size=100MB, getUsedStorageSize returns capacity+1
        $storageRepository->method('getUsedStorageSize')
            ->willReturn(1 * 1024 * 1024 * 1024 + 1);

        $urlDownloader = $this->createStub(UrlVideoDownloader::class);
        $urlDownloader->method('headRequest')->willReturn(['content-length' => (string)(100 * 1024 * 1024)]);

        $handler = $this->makeHandler($bus, $bus, $videoRepository, $urlDownloader, $userRepository, $storageRepository);
        $handler->__invoke(new VideoUpload($video, 'https://example.com/video.mp4'));

        // Last saved video must be marked deleted
        $this->assertNotEmpty($savedVideos);
        $this->assertTrue($savedVideos[array_key_last($savedVideos)]->isDeleted());

        // VideoUploadedFail must be dispatched
        $found = array_filter($dispatched, static fn($m) => $m instanceof VideoUploadedFail);
        $this->assertNotEmpty($found, 'VideoUploadedFail was not dispatched on quota exceeded');
    }
}
