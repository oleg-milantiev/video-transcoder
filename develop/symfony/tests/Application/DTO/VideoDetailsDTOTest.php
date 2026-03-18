<?php

declare(strict_types=1);

namespace App\Tests\Application\DTO;

use App\Application\DTO\PresetWithTaskDTO;
use App\Application\DTO\TaskInfoDTO;
use App\Application\DTO\VideoDetailsDTO;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoStatus;
use App\Domain\Video\ValueObject\VideoTitle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV4;

class VideoDetailsDTOTest extends TestCase
{
    public function testFromDomainSanitizesMetaAndKeepsPresets(): void
    {
        $uuid = UuidV4::fromString('33333333-3333-4333-8333-333333333333');
        $meta = [
            'duration' => 120,
            'preview' => true,
            'bitrate' => '5Mbps',
        ];
        $video = new Video(
            new VideoTitle('Detailed Video'),
            new FileExtension('mov'),
            VideoStatus::UPLOADED,
            7,
            new \DateTimeImmutable('2026-03-18 08:45:00'),
            $meta,
            $uuid,
        );

        $presetDto = new PresetWithTaskDTO(
            id: 5,
            name: 'Mobile',
            task: new TaskInfoDTO('PENDING', 0, '2026-03-18 08:50'),
        );

        $dto = VideoDetailsDTO::fromDomain($video, [$presetDto]);

        $this->assertSame($uuid->toRfc4122(), $dto->id);
        $this->assertSame('Detailed Video', $dto->title);
        $this->assertSame('mov', $dto->extension);
        $this->assertSame('UPLOADED', $dto->status);
        $this->assertSame('2026-03-18 08:45', $dto->createdAt);
        $this->assertNull($dto->updatedAt);
        $this->assertSame(7, $dto->userId);
        $this->assertSame([$presetDto], $dto->presetsWithTasks);
        $this->assertSame($uuid->toRfc4122() . '.jpg', $dto->poster);
        $this->assertArrayHasKey('duration', $dto->meta);
        $this->assertArrayHasKey('bitrate', $dto->meta);
        $this->assertArrayNotHasKey('preview', $dto->meta);
    }
}
