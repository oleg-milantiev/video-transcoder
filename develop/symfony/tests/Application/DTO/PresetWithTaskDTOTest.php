<?php

declare(strict_types=1);

namespace App\Tests\Application\DTO;

use App\Application\DTO\PresetWithTaskDTO;
use App\Application\DTO\TaskInfoDTO;
use PHPUnit\Framework\TestCase;

class PresetWithTaskDTOTest extends TestCase
{
    public function testHoldsPresetDataAndOptionalTask(): void
    {
        $task = new TaskInfoDTO(
            status: 'COMPLETED',
            progress: 100,
            createdAt: '2026-03-18 08:00',
            downloadFilename: 'completed-file.mp4',
            waitingTariffInstance: false,
            waitingTariffDelay: false,
            willStartAt: null,
        );
        $dto = new PresetWithTaskDTO('11111111-1111-4111-8111-111111111111', '4K', 999333, task: $task);

        $this->assertSame('11111111-1111-4111-8111-111111111111', $dto->id);
        $this->assertSame('4K', $dto->title);
        $this->assertSame(999333, $dto->expectedFileSize);
        $this->assertSame($task, $dto->task);
        $this->assertFalse($dto->task->waitingTariffInstance);
        $this->assertFalse($dto->task->waitingTariffDelay);
        $this->assertNull($dto->task->willStartAt);
    }
}
