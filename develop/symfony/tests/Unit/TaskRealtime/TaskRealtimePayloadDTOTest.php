<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaskRealtime;

use App\Application\DTO\TaskRealtimePayloadDTO;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\ValueObject\PresetTitle;
use App\Domain\Video\ValueObject\VideoCodec;
use App\Domain\Video\ValueObject\AudioCodec;
use App\Domain\Video\ValueObject\Format;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskDates;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Domain\Video\ValueObject\VideoTitle;
use PHPUnit\Framework\TestCase;

final class TaskRealtimePayloadDTOTest extends TestCase
{
    public function testFromTaskCreatesExpectedPayload(): void
    {
        $videoId = Uuid::generate();
        $presetId = Uuid::generate();
        $userId = Uuid::generate();

        $task = Task::reconstitute(
            $videoId,
            $presetId,
            $userId,
            TaskStatus::processing(),
            new Progress(42),
            TaskDates::create(),
            Uuid::generate(),
            [],
            false,
        );

        $dto = TaskRealtimePayloadDTO::fromTask($task);

        $arr = $dto->toArray();

        $this->assertArrayHasKey('taskId', $arr);
        $this->assertSame($task->id()->toRfc4122(), $arr['taskId']);
        $this->assertSame('PROCESSING', $arr['status']);
        $this->assertSame(42, $arr['progress']);
        $this->assertArrayHasKey('createdAt', $arr);
        $this->assertArrayHasKey('updatedAt', $arr);
        $this->assertFalse($arr['deleted']);
    }

    public function testAddVideoPresetFields(): void
    {
        $createdAt = new \DateTimeImmutable('2026-03-28 10:00:00', new \DateTimeZone('UTC'));
        $dto = new TaskRealtimePayloadDTO(
            taskId: Uuid::generate()->toRfc4122(),
            videoId: Uuid::generate()->toRfc4122(),
            presetId: Uuid::generate()->toRfc4122(),
            status: 'PENDING',
            progress: 0,
            createdAt: $createdAt->format(\DateTimeInterface::ATOM),
            updatedAt: null,
            deleted: false,
        );

        $video = Video::create(
            new VideoTitle('My video'),
            new FileExtension('mp4'),
            Uuid::generate(),
            [],
        );

        $preset = Preset::create(
            new PresetTitle('Test Preset'),
            new VideoCodec('h264'),
            new AudioCodec('aac'),
            new Format('mp4'),
        );

        $dto->addVideoPresetFields($video, $preset);

        $arr = $dto->toArray();

        $this->assertSame('My video', $arr['videoTitle']);
        $this->assertSame('h264/aac/mp4', $arr['presetTitle']);
    }
}
