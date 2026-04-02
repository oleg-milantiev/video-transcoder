<?php

declare(strict_types=1);

namespace App\Tests\Application\DTO;

use App\Application\DTO\TaskInfoDTO;
use PHPUnit\Framework\TestCase;

class TaskInfoDTOTest extends TestCase
{
    public function testStoresPrimitiveValues(): void
    {
        $createdAt = new \DateTimeImmutable('2026-03-18 09:00:00', new \DateTimeZone('UTC'));
        $willStartAt = new \DateTimeImmutable('2026-03-18 09:05:00', new \DateTimeZone('UTC'));
        $dto = new TaskInfoDTO(
            status: 'PROCESSING',
            progress: 65,
            createdAt: $createdAt->format(\DateTimeInterface::ATOM),
            downloadFilename: 'example.mp4',
            waitingTariffInstance: true,
            waitingTariffDelay: false,
            willStartAt: $willStartAt->format(\DateTimeInterface::ATOM),
            id: '10101010-1010-4010-8010-101010101010',
        );

        $this->assertSame('PROCESSING', $dto->status);
        $this->assertSame(65, $dto->progress);
        $this->assertSame($createdAt->format(\DateTimeInterface::ATOM), $dto->createdAt);
        $this->assertTrue($dto->waitingTariffInstance);
        $this->assertFalse($dto->waitingTariffDelay);
        $this->assertSame($willStartAt->format(\DateTimeInterface::ATOM), $dto->willStartAt);
        $this->assertSame('10101010-1010-4010-8010-101010101010', $dto->id);
    }

    public function testSchedulingFieldsAndIdAreOptional(): void
    {
        $dto = new TaskInfoDTO(
            status: 'PENDING',
            progress: 0,
            createdAt: (new \DateTimeImmutable('2026-03-18 10:00:00', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM),
            downloadFilename: 'file-placeholder.mp4',
            waitingTariffInstance: null,
            waitingTariffDelay: null,
            willStartAt: null,
        );

        $this->assertNull($dto->waitingTariffInstance);
        $this->assertNull($dto->waitingTariffDelay);
        $this->assertNull($dto->willStartAt);
        $this->assertNull($dto->id);
    }
}
