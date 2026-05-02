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
use App\Domain\Video\DTO\PaginatedResult;
use App\Domain\Video\Entity\Video;
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
    ): VideoUploadHandler {
        return new VideoUploadHandler(
            $commandBus,
            $eventBus,
            $videoRepository,
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
        $eventBus = $this->createStub(MessageBusInterface::class);
        $eventBus->method('dispatch')->willReturnCallback(static fn($m) => new Envelope($m));

        $savedVideos = [];
        $videoRepository = $this->makeVideoRepository($savedVideos);

        $urlDownloader = $this->createStub(UrlVideoDownloader::class);
        $urlDownloader->method('download')->willReturn([
            'path' => $tempFile,
            'filename' => 'my_video.mp4',
            'size' => 15,
        ]);

        $handler = $this->makeHandler($commandBus, $eventBus, $videoRepository, $urlDownloader);
        $handler->__invoke(new VideoUpload($video, 'https://example.com/my_video.mp4'));

        // Video must have been saved after markLoaded
        $this->assertCount(1, $savedVideos);
        $this->assertFalse($savedVideos[0]->isLoading());

        // VideoUploaded must be dispatched with correct filePath and filename
        $uploadedCmds = array_values(array_filter(
            $dispatched,
            static fn(object $m): bool => $m instanceof VideoUploaded,
        ));
        $this->assertCount(1, $uploadedCmds);
        $this->assertSame($tempFile, $uploadedCmds[0]->filePath());
        $this->assertSame('my_video.mp4', $uploadedCmds[0]->filename());
        $this->assertArrayHasKey('downloadUrl', $uploadedCmds[0]->additionalMeta());

        @unlink($tempFile);
    }

    /** При ошибке загрузки (download exception) диспатчится VideoUploadedFail. */
    public function testDownloadFailureDispatchesVideoUploadedFail(): void
    {
        $userId = Uuid::generate();
        $video = $this->makeVideo($userId);

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
        $urlDownloader->method('download')->willThrowException(new \RuntimeException('Connection refused'));

        $handler = $this->makeHandler($commandBus, $eventBus, $videoRepository, $urlDownloader);
        $handler->__invoke(new VideoUpload($video, 'https://example.com/video.mp4'));

        // No save should occur on failure
        $this->assertCount(0, $savedVideos);

        $found = false;
        foreach ($dispatched as $msg) {
            if ($msg instanceof VideoUploadedFail && str_contains($msg->error, 'Connection refused')) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'VideoUploadedFail was not dispatched on download failure');
    }
}

