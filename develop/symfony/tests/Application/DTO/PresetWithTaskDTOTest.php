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
        $task = new TaskInfoDTO('COMPLETED', 100, '2026-03-18 08:00');
        $dto = new PresetWithTaskDTO(1, '4K', $task);

        $this->assertSame(1, $dto->id);
        $this->assertSame('4K', $dto->name);
        $this->assertSame($task, $dto->task);
    }
}

