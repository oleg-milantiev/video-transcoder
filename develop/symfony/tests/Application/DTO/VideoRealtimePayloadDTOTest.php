<?php

declare(strict_types=1);

namespace App\Tests\Application\DTO;

use App\Application\DTO\VideoRealtimePayloadDTO;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskDates;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoTitle;
use PHPUnit\Framework\TestCase;

final class VideoRealtimePayloadDTOTest extends TestCase
{
    public function testFromVideoCreatesPayloadWithCanBeDeletedTrue(): void
    {
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $userId = Uuid::fromString('22222222-2222-4222-8222-222222222222');

        $video = Video::reconstitute(
            new VideoTitle('Realtime Video'),
            new FileExtension('mp4'),
            $userId,
            ['duration' => 120],
            VideoDates::create(new \DateTimeImmutable('2026-03-28 10:00:00')),
            $videoId,
        );

        $completedTask = Task::reconstitute(
            videoId: $videoId,
            presetId: Uuid::fromString('33333333-3333-4333-8333-333333333333'),
            userId: $userId,
            status: TaskStatus::COMPLETED,
            progress: new Progress(100),
            dates: TaskDates::create(),
            id: Uuid::fromString('44444444-4444-4444-8444-444444444444'),
        );

        $dto = VideoRealtimePayloadDTO::fromVideo($video, '/uploads/poster.jpg', [$completedTask]);

        $this->assertSame($videoId->toRfc4122(), $dto->videoId);
        $this->assertSame('Realtime Video', $dto->title);
        $this->assertSame('/uploads/poster.jpg', $dto->poster);
        $this->assertSame(['duration' => 120], $dto->meta);
        $this->assertFalse($dto->deleted);
        $this->assertTrue($dto->canBeDeleted);
        $this->assertStringContainsString('2026-03-28', $dto->createdAt);
    }

    public function testFromVideoWithActiveTaskCannotBeDeleted(): void
    {
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $userId = Uuid::fromString('22222222-2222-4222-8222-222222222222');

        $video = Video::reconstitute(
            new VideoTitle('Active Video'),
            new FileExtension('mp4'),
            $userId,
            [],
            VideoDates::create(),
            $videoId,
        );

        $processingTask = Task::reconstitute(
            videoId: $videoId,
            presetId: Uuid::fromString('33333333-3333-4333-8333-333333333333'),
            userId: $userId,
            status: TaskStatus::PROCESSING,
            progress: new Progress(50),
            dates: TaskDates::create(),
            id: Uuid::fromString('44444444-4444-4444-8444-444444444444'),
        );

        $dto = VideoRealtimePayloadDTO::fromVideo($video, null, [$processingTask]);

        $this->assertFalse($dto->canBeDeleted);
        $this->assertNull($dto->poster);
    }

    public function testToArrayIncludesAllFields(): void
    {
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $userId = Uuid::fromString('22222222-2222-4222-8222-222222222222');

        $video = Video::reconstitute(
            new VideoTitle('Payload Video'),
            new FileExtension('mkv'),
            $userId,
            ['sourceKey' => 'videos/source.mkv'],
            VideoDates::create(new \DateTimeImmutable('2026-03-28 10:00:00')),
            $videoId,
        );

        $dto = VideoRealtimePayloadDTO::fromVideo($video, '/uploads/thumb.jpg', []);

        $array = $dto->toArray();

        $this->assertIsArray($array);
        $this->assertSame($videoId->toRfc4122(), $array['videoId']);
        $this->assertSame('Payload Video', $array['title']);
        $this->assertSame('/uploads/thumb.jpg', $array['poster']);
        $this->assertSame(['sourceKey' => 'videos/source.mkv'], $array['meta']);
        $this->assertFalse($array['deleted']);
        $this->assertTrue($array['canBeDeleted']);
        $this->assertArrayHasKey('createdAt', $array);
        $this->assertArrayHasKey('updatedAt', $array);
    }

    public function testFromVideoWithDeletedVideo(): void
    {
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $userId = Uuid::fromString('22222222-2222-4222-8222-222222222222');

        $video = Video::reconstitute(
            new VideoTitle('Deleted'),
            new FileExtension('mp4'),
            $userId,
            [],
            VideoDates::create(),
            $videoId,
            true, // deleted
        );

        $dto = VideoRealtimePayloadDTO::fromVideo($video, null, []);

        $this->assertTrue($dto->deleted);
        $array = $dto->toArray();
        $this->assertTrue($array['deleted']);
    }
}
