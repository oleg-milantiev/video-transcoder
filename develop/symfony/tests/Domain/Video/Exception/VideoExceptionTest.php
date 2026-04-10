<?php

declare(strict_types=1);

namespace App\Tests\Domain\Video\Exception;

use App\Domain\Video\Exception\IncompatibleVideoFormat;
use App\Domain\Video\Exception\InvalidPresetName;
use App\Domain\Video\Exception\InvalidPresetTitle;
use App\Domain\Video\Exception\InvalidProgress;
use App\Domain\Video\Exception\InvalidTaskDates;
use App\Domain\Video\Exception\InvalidVideoDates;
use App\Domain\Video\Exception\InvalidVideoTitle;
use App\Domain\Video\Exception\TaskAlreadyDeleted;
use App\Domain\Video\Exception\UnsupportedCodec;
use App\Domain\Video\Exception\VideoAlreadyDeleted;
use App\Domain\Video\Exception\VideoHasTranscodingTasks;
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
}

