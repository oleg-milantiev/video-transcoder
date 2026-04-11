<?php

declare(strict_types=1);

namespace App\Tests\Application\DTO;

use App\Application\DTO\PresetWithTaskDTO;
use App\Application\DTO\TaskInfoDTO;
use App\Application\DTO\VideoDetailsDTO;
use App\Domain\User\Entity\Tariff;
use App\Domain\User\ValueObject\TariffStorageHour;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoTitle;
use PHPUnit\Framework\TestCase;
use App\Domain\Shared\ValueObject\Uuid;

class VideoDetailsDTOTest extends TestCase
{
    public function testFromDomainSanitizesMetaAndKeepsPresets(): void
    {
        $uuid = Uuid::fromString('33333333-3333-4333-8333-333333333333');
        $createdAt = new \DateTimeImmutable('2026-03-18 08:45:00', new \DateTimeZone('UTC'));
        $willStartAt = new \DateTimeImmutable('2026-03-18 09:20:00', new \DateTimeZone('UTC'));
        $meta = [
            'duration' => 120,
            'preview' => true,
            'bitrate' => '5Mbps',
        ];
        $video = Video::reconstitute(
            new VideoTitle('Detailed Video'),
            new FileExtension('mov'),
            Uuid::fromString('123e4567-e89b-42d3-a456-426614174007'),
            $meta,
            VideoDates::create($createdAt),
            $uuid,
        );

        $presetDto = new PresetWithTaskDTO(
            id: '123e4567-e89b-42d3-a456-426614174005',
            title: 'Mobile',
            expectedFileSize: 999333,
            task: new TaskInfoDTO(
                status: 'PENDING',
                progress: 0,
                createdAt: new \DateTimeImmutable('2026-03-18 08:50:00', new \DateTimeZone('UTC'))->format(\DateTimeInterface::ATOM),
                waitingTariffInstance: true,
                waitingTariffDelay: true,
                willStartAt: $willStartAt->format(\DateTimeInterface::ATOM),
                id: '123e4567-e89b-42d3-a456-426614174055',
            ),
        );

        $storage = $this->createStub(StorageInterface::class);
        $storage->method('previewKey')->willReturn($uuid->toRfc4122() . '.jpg');
        $storage->method('publicUrl')->willReturn('/uploads/' . $uuid->toRfc4122() . '.jpg');

        $tariff = $this->createStub(Tariff::class);
        $tariff->method('storageHour')->willReturn(new TariffStorageHour(24));

        $dto = VideoDetailsDTO::fromDomain($video, [$presetDto], $storage, $tariff);

        $this->assertSame($uuid->toRfc4122(), $dto->id);
        $this->assertSame('Detailed Video', $dto->title);
        $this->assertSame('mov', $dto->extension);
        $this->assertSame($createdAt->format(\DateTimeInterface::ATOM), $dto->createdAt);
        $this->assertSame($createdAt->add(new \DateInterval('PT24H'))->format(\DateTimeInterface::ATOM), $dto->expiredAt);
        $this->assertSame('expired', $dto->expiredInterval);
        $this->assertNull($dto->updatedAt);
        $this->assertSame([$presetDto], $dto->presetsWithTasks);
        $this->assertSame(999333, $dto->presetsWithTasks[0]->expectedFileSize);
        $this->assertTrue($dto->presetsWithTasks[0]->task->waitingTariffInstance);
        $this->assertTrue($dto->presetsWithTasks[0]->task->waitingTariffDelay);
        $this->assertSame($willStartAt->format(\DateTimeInterface::ATOM), $dto->presetsWithTasks[0]->task->willStartAt);
        $this->assertSame('/uploads/' . $uuid->toRfc4122() . '.jpg', $dto->poster);
        $this->assertArrayHasKey('duration', $dto->meta);
        $this->assertArrayHasKey('bitrate', $dto->meta);
        $this->assertArrayNotHasKey('preview', $dto->meta);
    }
}
