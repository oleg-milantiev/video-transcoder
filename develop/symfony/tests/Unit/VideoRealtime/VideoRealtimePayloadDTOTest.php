<?php

declare(strict_types=1);

namespace App\Tests\Unit\VideoRealtime;

use App\Application\DTO\VideoRealtimePayloadDTO;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskDates;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoTitle;
use PHPUnit\Framework\TestCase;

final class VideoRealtimePayloadDTOTest extends TestCase
{
    public function testFromVideoCreatesExpectedPayload(): void
    {
        $videoId = Uuid::generate();
        $video = Video::reconstitute(
            title: new VideoTitle('Clip'),
            extension: new FileExtension('mp4'),
            userId: Uuid::generate(),
            meta: ['preview' => true, 'duration' => 5.2],
            dates: VideoDates::create(),
            id: $videoId,
            deleted: true,
        );

        $dto = VideoRealtimePayloadDTO::fromVideo($video, '/uploads/preview.jpg', []);

        $arr = $dto->toArray();

        $this->assertSame($videoId->toRfc4122(), $arr['videoId']);
        $this->assertSame('Clip', $arr['title']);
        $this->assertSame('/uploads/preview.jpg', $arr['poster']);
        $this->assertSame(['preview' => true, 'duration' => 5.2], $arr['meta']);
        $this->assertTrue($arr['deleted']);
        $this->assertIsString($arr['createdAt']);
        $this->assertNotFalse(date_create_immutable($arr['createdAt']));
    }

    public function testFromVideoCanBeDeletedFalseWhenActiveTask(): void
    {
        $videoId = Uuid::generate();
        $video = Video::reconstitute(
            title: new VideoTitle('Clip2'),
            extension: new FileExtension('mp4'),
            userId: Uuid::generate(),
            meta: [],
            dates: VideoDates::create(),
            id: $videoId,
        );

        $activeTask = Task::reconstitute(
            videoId: $videoId,
            presetId: Uuid::generate(),
            userId: Uuid::generate(),
            status: TaskStatus::PENDING,
            progress: new Progress(0),
            dates: TaskDates::create(),
            id: Uuid::generate(),
        );

        $dto = VideoRealtimePayloadDTO::fromVideo($video, null, [$activeTask]);

        $this->assertFalse($dto->canBeDeleted);
        $this->assertNull($dto->poster);
    }
}
