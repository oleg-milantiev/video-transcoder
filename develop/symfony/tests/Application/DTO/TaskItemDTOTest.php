<?php

declare(strict_types=1);

namespace App\Tests\Application\DTO;

use App\Application\DTO\TaskItemDTO;
use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\ValueObject\Bitrate;
use App\Domain\Video\ValueObject\Codec;
use App\Domain\Video\ValueObject\PresetTitle;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\Resolution;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Domain\Video\ValueObject\TaskDates;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoTitle;
use App\Domain\Video\ValueObject\FileExtension;
use PHPUnit\Framework\TestCase;
use App\Domain\Shared\ValueObject\Uuid;

class TaskItemDTOTest extends TestCase
{
    public function testFromDomainUsesProvidedEntities(): void
    {
        $videoId = Uuid::fromString('22222222-2222-4222-8222-222222222222');
        $video = Video::reconstitute(
            new VideoTitle('Task Source Video'),
            new FileExtension('mkv'),
            Uuid::fromString('99999999-9999-4999-8999-999999999999'),
            [],
            VideoDates::create(new \DateTimeImmutable('2026-03-18 09:00:00')),
            $videoId,
        );

        $preset = new Preset(
            new PresetTitle('HD 1080p'),
            new Resolution(1920, 1080),
            new Codec('h264'),
            new Bitrate(50.0),
            Uuid::fromString('77777777-7777-4777-8777-777777777777'),
        );

        $task = Task::reconstitute(
            videoId: $videoId,
            presetId: Uuid::fromString('77777777-7777-4777-8777-777777777777'),
            userId: Uuid::fromString('99999999-9999-4999-8999-999999999999'),
            status: TaskStatus::processing(),
            progress: new Progress(75),
            dates: TaskDates::fromPersistence(new \DateTimeImmutable('2026-03-18 10:00:00'), null, null),
            id: Uuid::fromString('55555555-5555-4555-8555-555555555555'),
        );

        $dto = TaskItemDTO::fromDomain($task, $video, $preset);

        $this->assertSame('55555555-5555-4555-8555-555555555555', $dto->id);
        $this->assertSame('Task Source Video', $dto->videoTitle);
        $this->assertSame('HD 1080p', $dto->presetTitle);
        $this->assertSame('PROCESSING', $dto->status);
        $this->assertSame(75, $dto->progress);
        $this->assertSame('2026-03-18 10:00', $dto->createdAt);
    }

    public function testFromDomainThrowsWhenTaskHasNoId(): void
    {
        $video = Video::reconstitute(
            new VideoTitle('Video'),
            new FileExtension('mp4'),
            Uuid::fromString('99999999-9999-4999-8999-999999999999'),
            [],
            VideoDates::create(),
            Uuid::fromString('22222222-2222-4222-8222-222222222222'),
        );

        $preset = new Preset(
            new PresetTitle('HD 1080p'),
            new Resolution(1920, 1080),
            new Codec('h264'),
            new Bitrate(50.0),
            Uuid::fromString('77777777-7777-4777-8777-777777777777'),
        );

        // Task::create() produces a task with null id
        $task = Task::create(
            $video->id(),
            $preset->id(),
            Uuid::fromString('99999999-9999-4999-8999-999999999999'),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Task id must be set for TaskItemDTO mapping.');
        TaskItemDTO::fromDomain($task, $video, $preset);
    }
}
