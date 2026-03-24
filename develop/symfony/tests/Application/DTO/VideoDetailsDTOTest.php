<?php

declare(strict_types=1);

namespace App\Tests\Application\DTO;

use App\Application\DTO\PresetWithTaskDTO;
use App\Application\DTO\TaskInfoDTO;
use App\Application\DTO\VideoDetailsDTO;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\FileExtension;
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
        $video = Video::reconstitute(
            new VideoTitle('Detailed Video'),
            new FileExtension('mov'),
            UuidV4::fromString('123e4567-e89b-42d3-a456-426614174007'),
            $meta,
            \App\Domain\Video\ValueObject\VideoDates::create(new \DateTimeImmutable('2026-03-18 08:45:00')),
            $uuid,
        );

        $presetDto = new PresetWithTaskDTO(
            id: '123e4567-e89b-42d3-a456-426614174005',
            title: 'Mobile',
            task: new TaskInfoDTO('PENDING', 0, '2026-03-18 08:50', '123e4567-e89b-42d3-a456-426614174055'),
        );

        $storage = $this->createStub(StorageInterface::class);
        $storage->method('previewKey')->willReturn($uuid->toRfc4122() . '.jpg');
        $storage->method('publicUrl')->willReturn('/uploads/' . $uuid->toRfc4122() . '.jpg');

        $dto = VideoDetailsDTO::fromDomain($video, [$presetDto], $storage);

        $this->assertSame($uuid->toRfc4122(), $dto->id);
        $this->assertSame('Detailed Video', $dto->title);
        $this->assertSame('mov', $dto->extension);
        $this->assertSame('2026-03-18 08:45', $dto->createdAt);
        $this->assertNull($dto->updatedAt);
        $this->assertSame('123e4567-e89b-42d3-a456-426614174007', $dto->userId);
        $this->assertSame([$presetDto], $dto->presetsWithTasks);
        $this->assertSame('/uploads/' . $uuid->toRfc4122() . '.jpg', $dto->poster);
        $this->assertArrayHasKey('duration', $dto->meta);
        $this->assertArrayHasKey('bitrate', $dto->meta);
        $this->assertArrayNotHasKey('preview', $dto->meta);
    }
}
