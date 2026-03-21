<?php

declare(strict_types=1);

namespace App\Tests\Application\DTO;

use App\Application\DTO\TaskInfoDTO;
use PHPUnit\Framework\TestCase;

class TaskInfoDTOTest extends TestCase
{
    public function testStoresPrimitiveValues(): void
    {
        $dto = new TaskInfoDTO('PROCESSING', 65, '2026-03-18 09:00', '10101010-1010-4010-8010-101010101010');

        $this->assertSame('PROCESSING', $dto->status);
        $this->assertSame(65, $dto->progress);
        $this->assertSame('2026-03-18 09:00', $dto->createdAt);
        $this->assertSame('10101010-1010-4010-8010-101010101010', $dto->id);
    }

    public function testIdIsOptional(): void
    {
        $dto = new TaskInfoDTO('PENDING', 0, '2026-03-18 10:00');

        $this->assertNull($dto->id);
    }
}
