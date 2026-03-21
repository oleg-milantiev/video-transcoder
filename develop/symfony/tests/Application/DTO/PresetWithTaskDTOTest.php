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
        $dto = new PresetWithTaskDTO('11111111-1111-4111-8111-111111111111', '4K', task: $task);

        $this->assertSame('11111111-1111-4111-8111-111111111111', $dto->id);
        $this->assertSame('4K', $dto->title);
        $this->assertSame($task, $dto->task);
    }
}
