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
use Symfony\Component\Uid\UuidV4;

class TaskItemDTOTest extends TestCase
{
    public function testFromDomainUsesProvidedEntities(): void
    {
        $videoId = UuidV4::fromString('22222222-2222-4222-8222-222222222222');
        $video = Video::reconstitute(
            new VideoTitle('Task Source Video'),
            new FileExtension('mkv'),
            UuidV4::fromString('99999999-9999-4999-8999-999999999999'),
            [],
            VideoDates::create(new \DateTimeImmutable('2026-03-18 09:00:00')),
            $videoId,
        );

        $preset = new Preset(
            new PresetTitle('HD 1080p'),
            new Resolution(1920, 1080),
            new Codec('h264'),
            new Bitrate(50.0),
            UuidV4::fromString('77777777-7777-4777-8777-777777777777'),
        );

        $task = Task::reconstitute(
            videoId: $videoId,
            presetId: UuidV4::fromString('77777777-7777-4777-8777-777777777777'),
            userId: UuidV4::fromString('99999999-9999-4999-8999-999999999999'),
            status: TaskStatus::processing(),
            progress: new Progress(75),
            dates: TaskDates::fromPersistence(new \DateTimeImmutable('2026-03-18 10:00:00'), null, null),
            id: UuidV4::fromString('55555555-5555-4555-8555-555555555555'),
        );

        $dto = TaskItemDTO::fromDomain($task, $video, $preset);

        $this->assertSame('55555555-5555-4555-8555-555555555555', $dto->id);
        $this->assertSame('Task Source Video', $dto->videoTitle);
        $this->assertSame('HD 1080p', $dto->presetTitle);
        $this->assertSame('PROCESSING', $dto->status);
        $this->assertSame(75, $dto->progress);
        $this->assertSame('2026-03-18 10:00', $dto->createdAt);
    }
}
