<?php

declare(strict_types=1);

namespace App\Tests\Application\DTO;

use App\Application\DTO\TaskRealtimePayloadDTO;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\ValueObject\Bitrate;
use App\Domain\Video\ValueObject\Codec;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\PresetTitle;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\Resolution;
use App\Domain\Video\ValueObject\TaskDates;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoTitle;
use PHPUnit\Framework\TestCase;

final class TaskRealtimePayloadDTOTest extends TestCase
{
    public function testFromTaskCreatesPayload(): void
    {
        $taskId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $videoId = Uuid::fromString('22222222-2222-4222-8222-222222222222');
        $presetId = Uuid::fromString('33333333-3333-4333-8333-333333333333');
        $userId = Uuid::fromString('44444444-4444-4444-8444-444444444444');
        $createdAt = new \DateTimeImmutable('2026-03-28 10:00:00', new \DateTimeZone('UTC'));

        $task = Task::reconstitute(
            videoId: $videoId,
            presetId: $presetId,
            userId: $userId,
            status: TaskStatus::PROCESSING,
            progress: new Progress(75),
            dates: TaskDates::create($createdAt),
            id: $taskId,
        );

        $dto = TaskRealtimePayloadDTO::fromTask($task);

        $this->assertSame($taskId->toRfc4122(), $dto->taskId);
        $this->assertSame($videoId->toRfc4122(), $dto->videoId);
        $this->assertSame($presetId->toRfc4122(), $dto->presetId);
        $this->assertSame('PROCESSING', $dto->status);
        $this->assertSame(75, $dto->progress);
        $this->assertSame($createdAt->format(\DateTimeInterface::ATOM), $dto->createdAt);
        $this->assertFalse($dto->deleted);
    }

    public function testAddVideoPresetFieldsPopulatesFields(): void
    {
        $taskId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $videoId = Uuid::fromString('22222222-2222-4222-8222-222222222222');
        $presetId = Uuid::fromString('33333333-3333-4333-8333-333333333333');
        $userId = Uuid::fromString('44444444-4444-4444-8444-444444444444');

        $task = Task::reconstitute(
            videoId: $videoId,
            presetId: $presetId,
            userId: $userId,
            status: TaskStatus::COMPLETED,
            progress: new Progress(100),
            dates: TaskDates::create(),
            id: $taskId,
        );

        $video = Video::reconstitute(
            new VideoTitle('Test Video'),
            new FileExtension('mp4'),
            $userId,
            [],
            VideoDates::create(),
            $videoId,
        );

        $preset = new Preset(
            new PresetTitle('HD 720p'),
            new Resolution(1280, 720),
            new Codec('h264'),
            new Bitrate(50.0),
            id: $presetId,
        );

        $dto = TaskRealtimePayloadDTO::fromTask($task);
        $this->assertNull($dto->videoTitle);
        $this->assertNull($dto->presetTitle);

        $dto->addVideoPresetFields($video, $preset);

        $this->assertSame('Test Video', $dto->videoTitle);
        $this->assertSame('HD 720p', $dto->presetTitle);
    }

    public function testToArrayIncludesAllFields(): void
    {
        $taskId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $videoId = Uuid::fromString('22222222-2222-4222-8222-222222222222');
        $presetId = Uuid::fromString('33333333-3333-4333-8333-333333333333');
        $userId = Uuid::fromString('44444444-4444-4444-8444-444444444444');
        $createdAt = new \DateTimeImmutable('2026-03-28 10:00:00', new \DateTimeZone('UTC'));

        $task = Task::reconstitute(
            videoId: $videoId,
            presetId: $presetId,
            userId: $userId,
            status: TaskStatus::FAILED,
            progress: new Progress(50),
            dates: TaskDates::create($createdAt),
            id: $taskId,
        );

        $video = Video::reconstitute(
            new VideoTitle('My Video'),
            new FileExtension('mkv'),
            $userId,
            [],
            VideoDates::create(),
            $videoId,
        );

        $preset = new Preset(
            new PresetTitle('Full HD'),
            new Resolution(1920, 1080),
            new Codec('h265'),
            new Bitrate(100.0),
            id: $presetId,
        );

        $dto = TaskRealtimePayloadDTO::fromTask($task);
        $dto->addVideoPresetFields($video, $preset);

        $array = $dto->toArray();

        $this->assertIsArray($array);
        $this->assertSame($taskId->toRfc4122(), $array['taskId']);
        $this->assertSame($videoId->toRfc4122(), $array['videoId']);
        $this->assertSame($presetId->toRfc4122(), $array['presetId']);
        $this->assertSame('FAILED', $array['status']);
        $this->assertSame(50, $array['progress']);
        $this->assertFalse($array['deleted']);
        $this->assertSame('My Video', $array['videoTitle']);
        $this->assertSame('Full HD', $array['presetTitle']);
        $this->assertSame($createdAt->format(\DateTimeInterface::ATOM), $array['createdAt']);
        $this->assertArrayHasKey('updatedAt', $array);
    }

    public function testToArrayWithDeletedTask(): void
    {
        $taskId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $videoId = Uuid::fromString('22222222-2222-4222-8222-222222222222');
        $presetId = Uuid::fromString('33333333-3333-4333-8333-333333333333');
        $userId = Uuid::fromString('44444444-4444-4444-8444-444444444444');

        $task = Task::reconstitute(
            videoId: $videoId,
            presetId: $presetId,
            userId: $userId,
            status: TaskStatus::DELETED,
            progress: new Progress(0),
            dates: TaskDates::create(),
            id: $taskId,
            deleted: true,
        );

        $dto = TaskRealtimePayloadDTO::fromTask($task);
        $array = $dto->toArray();

        $this->assertTrue($array['deleted']);
    }
}
