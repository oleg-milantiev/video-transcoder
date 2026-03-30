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
        $this->assertSame('STARTING', TaskStatus::STARTING->name);
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

    public function testFactoryMethods(): void
    {
        $this->assertSame(TaskStatus::PENDING, TaskStatus::pending());
        $this->assertSame(TaskStatus::STARTING, TaskStatus::starting());
        $this->assertSame(TaskStatus::PROCESSING, TaskStatus::processing());
        $this->assertSame(TaskStatus::COMPLETED, TaskStatus::completed());
        $this->assertSame(TaskStatus::FAILED, TaskStatus::failed());
        $this->assertSame(TaskStatus::CANCELLED, TaskStatus::cancelled());
        $this->assertSame(TaskStatus::DELETED, TaskStatus::deleted());
    }

    public function testCanBeStarted(): void
    {
        $this->assertTrue(TaskStatus::STARTING->canBeStarted());
        $this->assertFalse(TaskStatus::PENDING->canBeStarted());
        $this->assertFalse(TaskStatus::PROCESSING->canBeStarted());
        $this->assertFalse(TaskStatus::COMPLETED->canBeStarted());
        $this->assertFalse(TaskStatus::FAILED->canBeStarted());
        $this->assertFalse(TaskStatus::CANCELLED->canBeStarted());
        $this->assertFalse(TaskStatus::DELETED->canBeStarted());
    }

    public function testCanBeDeleted(): void
    {
        $this->assertFalse(TaskStatus::PENDING->canBeDeleted());
        $this->assertFalse(TaskStatus::STARTING->canBeDeleted());
        $this->assertFalse(TaskStatus::PROCESSING->canBeDeleted());
        $this->assertTrue(TaskStatus::COMPLETED->canBeDeleted());
        $this->assertTrue(TaskStatus::FAILED->canBeDeleted());
        $this->assertTrue(TaskStatus::CANCELLED->canBeDeleted());
        $this->assertTrue(TaskStatus::DELETED->canBeDeleted());
    }

    public function testCanBeRestarted(): void
    {
        $this->assertTrue(TaskStatus::CANCELLED->canBeRestarted());
        $this->assertTrue(TaskStatus::FAILED->canBeRestarted());
        $this->assertFalse(TaskStatus::PENDING->canBeRestarted());
        $this->assertFalse(TaskStatus::STARTING->canBeRestarted());
        $this->assertFalse(TaskStatus::PROCESSING->canBeRestarted());
        $this->assertFalse(TaskStatus::COMPLETED->canBeRestarted());
        $this->assertFalse(TaskStatus::DELETED->canBeRestarted());
    }

    public function testIsTranscoding(): void
    {
        $this->assertTrue(TaskStatus::PENDING->isTranscoding());
        $this->assertTrue(TaskStatus::STARTING->isTranscoding());
        $this->assertTrue(TaskStatus::PROCESSING->isTranscoding());
        $this->assertFalse(TaskStatus::COMPLETED->isTranscoding());
        $this->assertFalse(TaskStatus::FAILED->isTranscoding());
        $this->assertFalse(TaskStatus::CANCELLED->isTranscoding());
        $this->assertFalse(TaskStatus::DELETED->isTranscoding());
    }

    public function testIsDeleted(): void
    {
        $this->assertTrue(TaskStatus::DELETED->isDeleted());
        $this->assertFalse(TaskStatus::PENDING->isDeleted());
        $this->assertFalse(TaskStatus::STARTING->isDeleted());
        $this->assertFalse(TaskStatus::PROCESSING->isDeleted());
        $this->assertFalse(TaskStatus::COMPLETED->isDeleted());
        $this->assertFalse(TaskStatus::FAILED->isDeleted());
        $this->assertFalse(TaskStatus::CANCELLED->isDeleted());
    }

    public function testIsFinished(): void
    {
        $this->assertTrue(TaskStatus::COMPLETED->isFinished());
        $this->assertTrue(TaskStatus::FAILED->isFinished());
        $this->assertTrue(TaskStatus::CANCELLED->isFinished());
        $this->assertTrue(TaskStatus::DELETED->isFinished());
        $this->assertFalse(TaskStatus::PENDING->isFinished());
        $this->assertFalse(TaskStatus::STARTING->isFinished());
        $this->assertFalse(TaskStatus::PROCESSING->isFinished());
    }
}

