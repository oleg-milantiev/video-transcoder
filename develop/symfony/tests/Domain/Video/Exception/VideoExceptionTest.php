<?php

declare(strict_types=1);

namespace App\Tests\Domain\Video\Exception;

use App\Domain\Video\Exception\IncompatibleVideoFormat;
use App\Domain\Video\Exception\InvalidPresetName;
use App\Domain\Video\Exception\InvalidPresetTitle;
use App\Domain\Video\Exception\InvalidProgress;
use App\Domain\Video\Exception\InvalidRealtimeNotification;
use App\Domain\Video\Exception\InvalidTaskDates;
use App\Domain\Video\Exception\InvalidVideoDates;
use App\Domain\Video\Exception\InvalidVideoTitle;
use App\Domain\Video\Exception\TaskAlreadyDeleted;
use App\Domain\Video\Exception\UnsupportedCodec;
use App\Domain\Video\Exception\VideoAlreadyDeleted;
use App\Domain\Video\Exception\VideoFileNotFound;
use App\Domain\Video\Exception\VideoHasTranscodingTasks;
use App\Domain\Video\Exception\VideoMetadataInvalid;
use App\Domain\Video\Exception\VideoSizeExceedsQuota;
use PHPUnit\Framework\TestCase;

final class VideoExceptionTest extends TestCase
{
    public function testIncompatibleVideoFormatMessage(): void
    {
        $exception = IncompatibleVideoFormat::fromValue('4K + 0.5Mbps');

        $this->assertInstanceOf(\DomainException::class, $exception);
        $this->assertSame('Incompatible Video Format: 4K + 0.5Mbps', $exception->getMessage());
    }

    public function testInvalidPresetNameMessage(): void
    {
        $exception = InvalidPresetName::fromValue('x');

        $this->assertInstanceOf(\DomainException::class, $exception);
        $this->assertSame('Invalid Preset Name: x', $exception->getMessage());
    }

    public function testInvalidPresetTitleMessage(): void
    {
        $exception = InvalidPresetTitle::fromValue('x');

        $this->assertInstanceOf(\DomainException::class, $exception);
        $this->assertSame('Invalid Preset Title: x', $exception->getMessage());
    }

    public function testInvalidProgressMessage(): void
    {
        $exception = InvalidProgress::outOfRange(101);

        $this->assertInstanceOf(\DomainException::class, $exception);
        $this->assertSame('Progress must be between 0 and 100, got 101.', $exception->getMessage());
    }

    public function testInvalidTaskDatesMessages(): void
    {
        $this->assertSame(
            'startedAt cannot be earlier than createdAt.',
            InvalidTaskDates::startedAtBeforeCreatedAt()->getMessage(),
        );
        $this->assertSame(
            'updatedAt cannot be earlier than createdAt.',
            InvalidTaskDates::updatedAtBeforeCreatedAt()->getMessage(),
        );
        $this->assertSame(
            'updatedAt cannot be earlier than startedAt.',
            InvalidTaskDates::updatedAtBeforeStartedAt()->getMessage(),
        );
        $this->assertSame(
            'Task is already started.',
            InvalidTaskDates::alreadyStarted()->getMessage(),
        );
    }

    public function testInvalidVideoDatesMessage(): void
    {
        $exception = InvalidVideoDates::updatedAtBeforeCreatedAt();

        $this->assertInstanceOf(\DomainException::class, $exception);
        $this->assertSame('updatedAt cannot be earlier than createdAt.', $exception->getMessage());
    }

    public function testInvalidVideoTitleMessages(): void
    {
        $this->assertSame('Video title cannot be empty.', InvalidVideoTitle::empty()->getMessage());
        $this->assertSame(
            'Video title must be less than 255 characters long.',
            InvalidVideoTitle::tooLong(255)->getMessage(),
        );
    }

    public function testUnsupportedCodecMessage(): void
    {
        $exception = UnsupportedCodec::fromValue('mpeg2');

        $this->assertInstanceOf(\DomainException::class, $exception);
        $this->assertSame('Unsupported codec: mpeg2', $exception->getMessage());
    }

    public function testDeleteRelatedMessages(): void
    {
        $this->assertSame('Video is already deleted.', VideoAlreadyDeleted::forVideo()->getMessage());
        $this->assertSame('Task is already deleted.', TaskAlreadyDeleted::forTask()->getMessage());
        $this->assertSame(
            'Video has active transcoding tasks and cannot be deleted.',
            VideoHasTranscodingTasks::forVideo()->getMessage(),
        );
    }

    public function testVideoFileNotFoundMessage(): void
    {
        $exception = VideoFileNotFound::cannotDetermineSize('/var/tmp/video.mp4');

        $this->assertInstanceOf(\DomainException::class, $exception);
        $this->assertSame('Cannot determine file size for: /var/tmp/video.mp4', $exception->getMessage());
    }

    public function testVideoMetadataInvalidMessages(): void
    {
        $this->assertInstanceOf(\DomainException::class, VideoMetadataInvalid::missingDuration());
        $this->assertSame(
            'Video metadata is missing duration information.',
            VideoMetadataInvalid::missingDuration()->getMessage(),
        );

        $this->assertSame(
            'Video duration 125.0 seconds exceeds your tariff limit of 60 seconds.',
            VideoMetadataInvalid::durationExceedsLimit(125.0, 60)->getMessage(),
        );

        $this->assertSame(
            'Video metadata is missing resolution (width/height) information.',
            VideoMetadataInvalid::missingResolution()->getMessage(),
        );

        $this->assertSame(
            'Video resolution 3840x2160 exceeds your tariff limit of 1920x1080.',
            VideoMetadataInvalid::resolutionExceedsLimit(3840, 2160, 1920, 1080)->getMessage(),
        );
    }

    public function testVideoSizeExceedsQuotaMessage(): void
    {
        $exception = VideoSizeExceedsQuota::fromSize(2048.5, 1024.0);

        $this->assertInstanceOf(\DomainException::class, $exception);
        $this->assertSame(
            'Video size 2048.5 MB exceeds your tariff limit of 1024.0 MB.',
            $exception->getMessage(),
        );
    }

    public function testInvalidRealtimeNotificationMessages(): void
    {
        $this->assertSame(
            'Realtime notification title cannot be empty.',
            InvalidRealtimeNotification::titleEmpty()->getMessage(),
        );
        $this->assertSame(
            'Realtime notification title cannot be longer than 140 characters.',
            InvalidRealtimeNotification::titleTooLong(140)->getMessage(),
        );
        $this->assertSame(
            'Realtime notification html cannot be empty.',
            InvalidRealtimeNotification::htmlEmpty()->getMessage(),
        );
        $this->assertSame(
            'Realtime notification timer must be between 500 and 60000 ms, got 99999.',
            InvalidRealtimeNotification::timerOutOfRange(99999, 500, 60000)->getMessage(),
        );
        $this->assertSame(
            'Realtime notification image URL is invalid: bad url',
            InvalidRealtimeNotification::invalidImageUrl('bad url')->getMessage(),
        );
    }
}

