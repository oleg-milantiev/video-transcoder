<?php

declare(strict_types=1);

namespace App\Tests\Application\DTO;

use App\Application\DTO\TaskItemDTO;
use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\ValueObject\Bitrate;
use App\Domain\Video\ValueObject\Codec;
use App\Domain\Video\ValueObject\PresetName;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\Resolution;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Domain\Video\ValueObject\VideoStatus;
use App\Domain\Video\ValueObject\VideoTitle;
use App\Domain\Video\ValueObject\FileExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV4;

class TaskItemDTOTest extends TestCase
{
    public function testFromDomainUsesProvidedEntities(): void
    {
        $videoId = UuidV4::fromString('22222222-2222-4222-8222-222222222222');
        $video = new Video(
            new VideoTitle('Task Source Video'),
            new FileExtension('mkv'),
            VideoStatus::UPLOADED,
            99,
            new \DateTimeImmutable('2026-03-18 09:00:00'),
            [],
            $videoId,
        );

        $preset = new Preset(
            new PresetName('HD 1080p'),
            new Resolution(1920, 1080),
            new Codec('h264'),
            new Bitrate(50.0),
            7,
        );

        $task = new Task(
            videoId: $videoId,
            presetId: 7,
            userId: 99,
            status: TaskStatus::processing(),
            progress: new Progress(75),
            createdAt: new \DateTimeImmutable('2026-03-18 10:00:00'),
        );

        $dto = TaskItemDTO::fromDomain($task, $video, $preset);

        $this->assertSame('Task Source Video', $dto->videoTitle);
        $this->assertSame('HD 1080p', $dto->presetTitle);
        $this->assertSame('PROCESSING', $dto->status);
        $this->assertSame(75, $dto->progress);
        $this->assertSame('2026-03-18 10:00', $dto->createdAt);
    }
}
