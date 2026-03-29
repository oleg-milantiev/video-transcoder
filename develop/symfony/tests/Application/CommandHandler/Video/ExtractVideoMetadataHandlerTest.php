<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler\Video;

use App\Application\Command\Video\ExtractVideoMetadata;
use App\Application\CommandHandler\Video\ExtractVideoMetadataHandler;
use App\Application\Event\ExtractVideoMetadataFail;
use App\Application\Event\ExtractVideoMetadataStart;
use App\Application\Service\Video\VideoRealtimeNotifier;
use App\Domain\User\Entity\Tariff;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\TariffMaxHeight;
use App\Domain\User\ValueObject\TariffMaxWidth;
use App\Domain\User\ValueObject\TariffVideoDuration;
use App\Domain\Video\ValueObject\VideoDates;
use App\Application\Logging\LogServiceInterface;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Exception\VideoMetadataExtractionFailed;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoTitle;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Infrastructure\Ffmpeg\ProcessRunnerInterface;
use App\Infrastructure\Ffmpeg\VideoMetadataExtractor;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Domain\Shared\ValueObject\Uuid;

class ExtractVideoMetadataHandlerTest extends TestCase
{
    private const VIDEO_ID = '123e4567-e89b-42d3-a456-426614174152';
    private const USER_ID = '123e4567-e89b-42d3-a456-426614174105';

    // ===== Helper Methods =====

    private function createVideoStub(
        array $meta = [],
        string $videoId = self::VIDEO_ID,
        string $userId = self::USER_ID
    ): Video {
        return Video::reconstitute(
            title: new VideoTitle('Test Video'),
            extension: new FileExtension('mp4'),
            userId: Uuid::fromString($userId),
            meta: $meta,
            dates: VideoDates::create(),
            id: Uuid::fromString($videoId),
        );
    }

    private function createUserWithTariff(
        int $maxDuration = 3600,
        int $maxWidth = 4096,
        int $maxHeight = 4096
    ) {
        $user = $this->createStub(User::class);
        $tariff = $this->createStub(Tariff::class);
        $tariff->method('videoDuration')->willReturn(new TariffVideoDuration($maxDuration));
        $tariff->method('maxWidth')->willReturn(new TariffMaxWidth($maxWidth));
        $tariff->method('maxHeight')->willReturn(new TariffMaxHeight($maxHeight));
        $user->method('tariff')->willReturn($tariff);
        $user->method('id')->willReturn(Uuid::fromString(self::USER_ID));

        return $user;
    }

    private function createMetadataExtractor(array $metadata = []): VideoMetadataExtractor
    {
        $defaultMetadata = [
            'format' => [
                'duration' => '10.5',
                'bit_rate' => '1200',
            ],
            'streams' => [[
                'codec_type' => 'video',
                'width' => 1920,
                'height' => 1080,
                'codec_name' => 'h264',
            ]],
        ];

        $processRunner = $this->createStub(ProcessRunnerInterface::class);
        $processRunner->method('mustRunAndGetOutput')->willReturn(
            json_encode(array_merge_recursive($defaultMetadata, $metadata), JSON_THROW_ON_ERROR)
        );

        return new VideoMetadataExtractor($processRunner);
    }

    private function createMetadataExtractorWithError(\Throwable $error): VideoMetadataExtractor
    {
        $processRunner = $this->createStub(ProcessRunnerInterface::class);
        $processRunner->method('mustRunAndGetOutput')->willThrowException($error);

        return new VideoMetadataExtractor($processRunner);
    }

    private function createHandler(
        Video $video,
        ?UserRepositoryInterface $userRepository = null,
        ?VideoRepositoryInterface $videoRepository = null,
        ?VideoMetadataExtractor $extractor = null,
        ?MessageBusInterface $eventBus = null,
        ?MessageBusInterface $commandBus = null,
        ?StorageInterface $storage = null,
        ?LogServiceInterface $logService = null,
        ?LoggerInterface $logger = null,
        ?TaskRepositoryInterface $taskRepository = null
    ): ExtractVideoMetadataHandler {
        $storage ??= $this->createMock(StorageInterface::class);
        $storage->expects($this->any())->method('sourceKey')->willReturn('source.mp4');
        $storage->expects($this->any())->method('localPathForRead')->willReturn('/tmp/source.mp4');

        return new ExtractVideoMetadataHandler(
            $videoRepository ?? $this->createMock(VideoRepositoryInterface::class),
            $userRepository ?? $this->createStub(UserRepositoryInterface::class),
            $taskRepository ?? $this->createStub(TaskRepositoryInterface::class),
            $storage,
            $commandBus ?? $this->createStub(MessageBusInterface::class),
            $eventBus ?? $this->createStub(MessageBusInterface::class),
            $extractor ?? $this->createMetadataExtractor(),
            new VideoRealtimeNotifier(
                $this->createStub(MessageBusInterface::class),
                $storage,
                $this->createStub(TaskRepositoryInterface::class)
            ),
            $logService ?? $this->createStub(LogServiceInterface::class),
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }

    // ===== Success Scenarios =====

    public function testSuccessfulMetadataExtractionWithValidation(): void
    {
        $video = $this->createVideoStub();
        $user = $this->createUserWithTariff();

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($user);

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('save')->willReturnCallback(static fn (Video $v) => $v);

        $logService = $this->createStub(LogServiceInterface::class);

        $storage = $this->createStub(StorageInterface::class);
        $storage->method('sourceKey')->willReturn('source.mp4');
        $storage->method('localPathForRead')->willReturn('/tmp/source.mp4');

        $eventBus = $this->createStub(MessageBusInterface::class);
        $eventBus->method('dispatch')->willReturnCallback(static fn () => new Envelope(new \stdClass()));

        $commandBus = $this->createStub(MessageBusInterface::class);
        $commandBus->method('dispatch')->willReturnCallback(static fn () => new Envelope(new \stdClass()));

        $notifierBus = $this->createStub(MessageBusInterface::class);
        $notifierBus->method('dispatch')->willReturnCallback(static fn () => new Envelope(new \stdClass()));

        $handler = new ExtractVideoMetadataHandler(
            $videoRepository,
            $userRepository,
            $this->createStub(TaskRepositoryInterface::class),
            $storage,
            $commandBus,
            $eventBus,
            $this->createMetadataExtractor(),
            new VideoRealtimeNotifier(
                $notifierBus,
                $storage,
                $this->createStub(TaskRepositoryInterface::class)
            ),
            $logService,
            $this->createStub(LoggerInterface::class),
        );

        $handler(new ExtractVideoMetadata($video));

        // Just verify it doesn't throw
        $this->assertTrue(true);
    }

    // ===== User Not Found =====

    public function testUserNotFoundDispatchesFailEvent(): void
    {
        $video = $this->createVideoStub();

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn(null);

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->atLeastOnce())->method('save');

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->atLeastOnce())->method('log');

        $eventBus = $this->createMock(MessageBusInterface::class);
        $events = [];
        $eventBus->method('dispatch')
            ->willReturnCallback(static function (object $msg) use (&$events): Envelope {
                $events[] = $msg::class;
                return new Envelope($msg);
            });

        $handler = $this->createHandler(
            $video,
            $userRepository,
            $videoRepository,
            null,
            $eventBus,
            null,
            null,
            $logService
        );

        $this->expectException(VideoMetadataExtractionFailed::class);

        try {
            $handler(new ExtractVideoMetadata($video));
        } finally {
            $this->assertContains(ExtractVideoMetadataStart::class, $events);
            $this->assertContains(ExtractVideoMetadataFail::class, $events);
        }
    }

    // ===== Tariff Not Found =====

    public function testTariffNotFoundDispatchesFailEvent(): void
    {
        $video = $this->createVideoStub();

        $user = $this->createStub(User::class);
        $user->method('tariff')->willReturn(null);
        $user->method('id')->willReturn(Uuid::fromString(self::USER_ID));

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($user);

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->atLeastOnce())->method('save');

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->atLeastOnce())->method('log');

        $eventBus = $this->createMock(MessageBusInterface::class);
        $events = [];
        $eventBus->method('dispatch')
            ->willReturnCallback(static function (object $msg) use (&$events): Envelope {
                $events[] = $msg::class;
                return new Envelope($msg);
            });

        $handler = $this->createHandler(
            $video,
            $userRepository,
            $videoRepository,
            null,
            $eventBus,
            null,
            null,
            $logService
        );

        $this->expectException(VideoMetadataExtractionFailed::class);

        try {
            $handler(new ExtractVideoMetadata($video));
        } finally {
            $this->assertContains(ExtractVideoMetadataStart::class, $events);
            $this->assertContains(ExtractVideoMetadataFail::class, $events);
        }
    }

    // ===== Missing Duration =====

    public function testMissingDurationDispatchesFailEvent(): void
    {
        $video = $this->createVideoStub();
        $user = $this->createUserWithTariff();

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($user);

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->atLeastOnce())->method('save');

        $eventBus = $this->createMock(MessageBusInterface::class);
        $events = [];
        $eventBus->method('dispatch')
            ->willReturnCallback(static function (object $msg) use (&$events): Envelope {
                $events[] = $msg::class;
                return new Envelope($msg);
            });

        $extractor = $this->createMetadataExtractor([
            'format' => ['duration' => null],
        ]);

        $handler = $this->createHandler($video, $userRepository, $videoRepository, $extractor, $eventBus);

        $this->expectException(VideoMetadataExtractionFailed::class);

        try {
            $handler(new ExtractVideoMetadata($video));
        } finally {
            $this->assertContains(ExtractVideoMetadataStart::class, $events);
            $this->assertContains(ExtractVideoMetadataFail::class, $events);
        }
    }

    // ===== Duration Exceeds Limit =====

    public function testDurationExceedsLimitDispatchesFailEvent(): void
    {
        $video = $this->createVideoStub();
        $user = $this->createUserWithTariff(maxDuration: 5);

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($user);

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->atLeastOnce())->method('save');

        $eventBus = $this->createMock(MessageBusInterface::class);
        $events = [];
        $eventBus->method('dispatch')
            ->willReturnCallback(static function (object $msg) use (&$events): Envelope {
                $events[] = $msg::class;
                return new Envelope($msg);
            });

        $extractor = $this->createMetadataExtractor([
            'format' => ['duration' => '10.5'],
        ]);

        $handler = $this->createHandler($video, $userRepository, $videoRepository, $extractor, $eventBus);

        $this->expectException(VideoMetadataExtractionFailed::class);

        try {
            $handler(new ExtractVideoMetadata($video));
        } finally {
            $this->assertContains(ExtractVideoMetadataStart::class, $events);
            $this->assertContains(ExtractVideoMetadataFail::class, $events);
        }
    }

    // ===== Missing Resolution =====

    public function testMissingWidthDispatchesFailEvent(): void
    {
        $video = $this->createVideoStub();
        $user = $this->createUserWithTariff();

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($user);

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->atLeastOnce())->method('save');

        $eventBus = $this->createMock(MessageBusInterface::class);
        $events = [];
        $eventBus->method('dispatch')
            ->willReturnCallback(static function (object $msg) use (&$events): Envelope {
                $events[] = $msg::class;
                return new Envelope($msg);
            });

        $extractor = $this->createMetadataExtractor([
            'streams' => [['codec_type' => 'video', 'width' => null, 'height' => 1080]],
        ]);

        $handler = $this->createHandler($video, $userRepository, $videoRepository, $extractor, $eventBus);

        $this->expectException(VideoMetadataExtractionFailed::class);

        try {
            $handler(new ExtractVideoMetadata($video));
        } finally {
            $this->assertContains(ExtractVideoMetadataStart::class, $events);
            $this->assertContains(ExtractVideoMetadataFail::class, $events);
        }
    }

    public function testMissingHeightDispatchesFailEvent(): void
    {
        $video = $this->createVideoStub();
        $user = $this->createUserWithTariff();

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($user);

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->atLeastOnce())->method('save');

        $eventBus = $this->createMock(MessageBusInterface::class);
        $events = [];
        $eventBus->method('dispatch')
            ->willReturnCallback(static function (object $msg) use (&$events): Envelope {
                $events[] = $msg::class;
                return new Envelope($msg);
            });

        $extractor = $this->createMetadataExtractor([
            'streams' => [['codec_type' => 'video', 'width' => 1920, 'height' => null]],
        ]);

        $handler = $this->createHandler($video, $userRepository, $videoRepository, $extractor, $eventBus);

        $this->expectException(VideoMetadataExtractionFailed::class);

        try {
            $handler(new ExtractVideoMetadata($video));
        } finally {
            $this->assertContains(ExtractVideoMetadataStart::class, $events);
            $this->assertContains(ExtractVideoMetadataFail::class, $events);
        }
    }

    // ===== Resolution Exceeds Limit =====

    public function testWidthExceedsLimitDispatchesFailEvent(): void
    {
        $video = $this->createVideoStub();
        $user = $this->createUserWithTariff(maxWidth: 1280);

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($user);

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->atLeastOnce())->method('save');

        $eventBus = $this->createMock(MessageBusInterface::class);
        $events = [];
        $eventBus->method('dispatch')
            ->willReturnCallback(static function (object $msg) use (&$events): Envelope {
                $events[] = $msg::class;
                return new Envelope($msg);
            });

        $extractor = $this->createMetadataExtractor([
            'streams' => [['codec_type' => 'video', 'width' => 1920, 'height' => 1080]],
        ]);

        $handler = $this->createHandler($video, $userRepository, $videoRepository, $extractor, $eventBus);

        $this->expectException(VideoMetadataExtractionFailed::class);

        try {
            $handler(new ExtractVideoMetadata($video));
        } finally {
            $this->assertContains(ExtractVideoMetadataStart::class, $events);
            $this->assertContains(ExtractVideoMetadataFail::class, $events);
        }
    }

    public function testHeightExceedsLimitDispatchesFailEvent(): void
    {
        $video = $this->createVideoStub();
        $user = $this->createUserWithTariff(maxHeight: 720);

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($user);

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->atLeastOnce())->method('save');

        $eventBus = $this->createMock(MessageBusInterface::class);
        $events = [];
        $eventBus->method('dispatch')
            ->willReturnCallback(static function (object $msg) use (&$events): Envelope {
                $events[] = $msg::class;
                return new Envelope($msg);
            });

        $extractor = $this->createMetadataExtractor([
            'streams' => [['codec_type' => 'video', 'width' => 1280, 'height' => 1080]],
        ]);

        $handler = $this->createHandler($video, $userRepository, $videoRepository, $extractor, $eventBus);

        $this->expectException(VideoMetadataExtractionFailed::class);

        try {
            $handler(new ExtractVideoMetadata($video));
        } finally {
            $this->assertContains(ExtractVideoMetadataStart::class, $events);
            $this->assertContains(ExtractVideoMetadataFail::class, $events);
        }
    }

    // ===== Extractor Error =====

    public function testExtractorErrorDispatchesFailEvent(): void
    {
        $video = $this->createVideoStub();
        $user = $this->createUserWithTariff();

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($user);

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->atLeastOnce())->method('save');

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->atLeastOnce())->method('log');

        $eventBus = $this->createMock(MessageBusInterface::class);
        $events = [];
        $eventBus->method('dispatch')
            ->willReturnCallback(static function (object $msg) use (&$events): Envelope {
                $events[] = $msg::class;
                return new Envelope($msg);
            });

        $extractor = $this->createMetadataExtractorWithError(new \RuntimeException('ffprobe timeout'));

        $handler = $this->createHandler(
            $video,
            $userRepository,
            $videoRepository,
            $extractor,
            $eventBus,
            null,
            null,
            $logService
        );

        $this->expectException(VideoMetadataExtractionFailed::class);

        try {
            $handler(new ExtractVideoMetadata($video));
        } finally {
            $this->assertContains(ExtractVideoMetadataStart::class, $events);
            $this->assertContains(ExtractVideoMetadataFail::class, $events);
        }
    }
}
