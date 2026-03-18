<?php

declare(strict_types=1);

namespace App\Tests\Application\DTO;

use App\Application\DTO\VideoItemDTO;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoStatus;
use App\Domain\Video\ValueObject\VideoTitle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV4;

class VideoItemDTOTest extends TestCase
{
    public function testFromDomainMapsAllFields(): void
    {
        $uuid = UuidV4::fromString('11111111-1111-4111-8111-111111111111');
        $video = new Video(
            new VideoTitle('Demo Video'),
            new FileExtension('mp4'),
            VideoStatus::UPLOADED,
            42,
            new \DateTimeImmutable('2026-03-18 10:15:00'),
            ['preview' => true],
            $uuid,
        );

        $dto = VideoItemDTO::fromDomain($video);

        $this->assertSame($uuid->toRfc4122(), $dto->uuid);
        $this->assertSame('Demo Video', $dto->title);
        $this->assertSame('UPLOADED', $dto->status);
        $this->assertSame('2026-03-18 10:15', $dto->createdAt);
        $this->assertSame($uuid->toRfc4122() . '.jpg', $dto->poster);
    }
}
