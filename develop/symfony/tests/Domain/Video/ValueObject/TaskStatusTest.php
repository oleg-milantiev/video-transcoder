<?php

declare(strict_types=1);

namespace Tests\Domain\Video\ValueObject;

use App\Domain\Video\ValueObject\TaskStatus;
use PHPUnit\Framework\TestCase;

class TaskStatusTest extends TestCase
{
    public function testEnumCases(): void
    {
        $this->assertSame('PENDING', TaskStatus::PENDING->name);
        $this->assertSame('PROCESSING', TaskStatus::PROCESSING->name);
        $this->assertSame('COMPLETED', TaskStatus::COMPLETED->name);
        $this->assertSame('FAILED', TaskStatus::FAILED->name);
        $this->assertSame('CANCELLED', TaskStatus::CANCELLED->name);
        $this->assertSame('DELETED', TaskStatus::DELETED->name);
    }

    public function testFromValue(): void
    {
        $this->assertSame(TaskStatus::PENDING, TaskStatus::from(TaskStatus::PENDING->value));
    }
}

