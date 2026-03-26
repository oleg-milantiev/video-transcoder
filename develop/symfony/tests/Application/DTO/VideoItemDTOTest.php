<?php

declare(strict_types=1);

namespace App\Tests\Application\DTO;

use App\Application\DTO\VideoItemDTO;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoTitle;
use PHPUnit\Framework\TestCase;
use App\Domain\Shared\ValueObject\Uuid;

class VideoItemDTOTest extends TestCase
{
    public function testFromDomainMapsAllFields(): void
    {
        $uuid = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $video = Video::reconstitute(
            new VideoTitle('Demo Video'),
            new FileExtension('mp4'),
            Uuid::fromString('42424242-4242-4242-8242-424242424242'),
            ['preview' => true],
            VideoDates::create(new \DateTimeImmutable('2026-03-18 10:15:00')),
            $uuid,
        );

        $storage = $this->createStub(StorageInterface::class);
        $storage->method('previewKey')->willReturn($uuid->toRfc4122() . '.jpg');
        $storage->method('publicUrl')->willReturn('/uploads/' . $uuid->toRfc4122() . '.jpg');

        $dto = VideoItemDTO::fromDomain($video, $storage);

        $this->assertSame($uuid->toRfc4122(), $dto->uuid);
        $this->assertSame('Demo Video', $dto->title);
        $this->assertSame('2026-03-18 10:15', $dto->createdAt);
        $this->assertFalse($dto->deleted);
        $this->assertSame('/uploads/' . $uuid->toRfc4122() . '.jpg', $dto->poster);
    }

    public function testFromDomainMapsDeletedVideo(): void
    {
        $uuid = Uuid::fromString('99999999-9999-4999-8999-999999999999');
        $video = Video::reconstitute(
            new VideoTitle('Removed Video'),
            new FileExtension('mp4'),
            Uuid::fromString('42424242-4242-4242-8242-424242424242'),
            ['preview' => true],
            VideoDates::create(new \DateTimeImmutable('2026-03-18 10:15:00')),
            $uuid,
            true,
        );

        $storage = $this->createStub(StorageInterface::class);
        $storage->method('previewKey')->willReturn($uuid->toRfc4122() . '.jpg');
        $storage->method('publicUrl')->willReturn('/uploads/' . $uuid->toRfc4122() . '.jpg');

        $dto = VideoItemDTO::fromDomain($video, $storage);

        $this->assertTrue($dto->deleted);
        $this->assertSame('/uploads/' . $uuid->toRfc4122() . '.jpg', $dto->poster);
    }
}
