<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler\Video;

use App\Application\Command\Video\CreateVideoPreview;
use App\Application\Command\Video\ExtractVideoMetadata;
use App\Application\CommandHandler\Video\ExtractVideoMetadataHandler;
use App\Application\Event\ExtractVideoMetadataFail;
use App\Application\Event\ExtractVideoMetadataStart;
use App\Application\Event\ExtractVideoMetadataSuccess;
use Psr\Log\LogLevel;
use App\Application\Logging\LogServiceInterface;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Exception\VideoMetadataExtractionFailed;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoStatus;
use App\Domain\Video\ValueObject\VideoTitle;
use App\Infrastructure\Ffmpeg\ProcessRunnerInterface;
use App\Infrastructure\Ffmpeg\VideoMetadataExtractor;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\UuidV4;

class ExtractVideoMetadataHandlerTest extends TestCase
{
    public function testExtractsMetadataDispatchesPreviewCommandAndSuccessEvent(): void
    {
        $video = $this->createVideo();

        $storage = $this->createMock(StorageInterface::class);
        $storage->expects($this->once())
            ->method('getAbsolutePath')
            ->with($video->getSrcFilename())
            ->willReturn('/tmp/source.mp4');

        $processRunner = $this->createMock(ProcessRunnerInterface::class);
        $processRunner->expects($this->once())
            ->method('mustRunAndGetOutput')
            ->with(VideoMetadataExtractor::buildCommand('/tmp/source.mp4'))
            ->willReturn((string) json_encode([
                'format' => [
                    'duration' => '10.5',
                    'bit_rate' => '1200',
                    'format_name' => 'mov,mp4,m4a,3gp,3g2,mj2',
                    'size' => '100000',
                ],
                'streams' => [[
                    'codec_type' => 'video',
                    'width' => 1920,
                    'height' => 1080,
                    'codec_name' => 'h264',
                    'avg_frame_rate' => '25/1',
                ]],
            ]));

        $extractor = new VideoMetadataExtractor($processRunner);

        $eventBus = $this->createMock(MessageBusInterface::class);
        $events = [];
        $eventBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$events): Envelope {
                $events[] = $message::class;

                return new Envelope($message);
            });

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CreateVideoPreview::class))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (Video $savedVideo): bool {
                return ($savedVideo->meta()['codec'] ?? null) === 'h264';
            }));
        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())
            ->method('log')
            ->with('video', $video->id(), LogLevel::INFO, 'Metadata extracted');

        $handler = new ExtractVideoMetadataHandler(
            $videoRepository,
            $storage,
            $commandBus,
            $eventBus,
            $extractor,
            $logService,
            $this->createStub(LoggerInterface::class),
        );

        $handler(new ExtractVideoMetadata($video));

        $this->assertSame([
            ExtractVideoMetadataStart::class,
            ExtractVideoMetadataSuccess::class,
        ], $events);
    }

    public function testDispatchesFailEventAndThrowsDomainExceptionOnExtractorError(): void
    {
        $video = $this->createVideo();

        $storage = $this->createStub(StorageInterface::class);
        $storage->method('getAbsolutePath')->willReturn('/tmp/source.mp4');

        $processRunner = $this->createStub(ProcessRunnerInterface::class);
        $processRunner->method('mustRunAndGetOutput')->willThrowException(new \RuntimeException('ffprobe timeout'));

        $extractor = new VideoMetadataExtractor($processRunner);

        $eventBus = $this->createMock(MessageBusInterface::class);
        $events = [];
        $eventBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$events): Envelope {
                $events[] = $message::class;

                return new Envelope($message);
            });

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->never())->method('dispatch');

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())
            ->method('log')
            ->with('video', $video->id(), LogLevel::ERROR, 'Metadata extraction error: ffprobe timeout');

        $handler = new ExtractVideoMetadataHandler(
            $videoRepository,
            $storage,
            $commandBus,
            $eventBus,
            $extractor,
            $logService,
            $this->createStub(LoggerInterface::class),
        );

        $this->expectException(VideoMetadataExtractionFailed::class);

        try {
            $handler(new ExtractVideoMetadata($video));
        } finally {
            $this->assertSame([
                ExtractVideoMetadataStart::class,
                ExtractVideoMetadataFail::class,
            ], $events);
        }
    }

    private function createVideo(): Video
    {
        return Video::create(
            title: new VideoTitle('Clip'),
            extension: new FileExtension('mp4'),
            status: VideoStatus::UPLOADED,
            userId: UuidV4::fromString('123e4567-e89b-42d3-a456-426614174105'),
            meta: [],
            id: UuidV4::fromString('123e4567-e89b-42d3-a456-426614174152'),
        );
    }
}

