<?php

declare(strict_types=1);

namespace Tests\Domain\Video\ValueObject;

use App\Domain\Video\Exception\InvalidTaskDates;
use App\Domain\Video\ValueObject\TaskDates;
use PHPUnit\Framework\TestCase;

class TaskDatesTest extends TestCase
{
    public function testCreateInitializesOnlyCreatedAt(): void
    {
        $createdAt = new \DateTimeImmutable('2026-03-18 10:00:00');

        $dates = TaskDates::create($createdAt);

        $this->assertSame($createdAt, $dates->createdAt());
        $this->assertNull($dates->startedAt());
        $this->assertNull($dates->updatedAt());
    }

    public function testMarkStartedSetsStartedAtAndUpdatedAt(): void
    {
        $createdAt = new \DateTimeImmutable('2026-03-18 10:00:00');
        $startedAt = new \DateTimeImmutable('2026-03-18 10:05:00');

        $dates = TaskDates::create($createdAt)->markStarted($startedAt);

        $this->assertSame($startedAt, $dates->startedAt());
        $this->assertSame($startedAt, $dates->updatedAt());
    }

    public function testTouchUpdatesUpdatedAt(): void
    {
        $createdAt = new \DateTimeImmutable('2026-03-18 10:00:00');
        $updatedAt = new \DateTimeImmutable('2026-03-18 10:10:00');

        $dates = TaskDates::create($createdAt)->touch($updatedAt);

        $this->assertSame($updatedAt, $dates->updatedAt());
        $this->assertNull($dates->startedAt());
    }

    public function testInvalidStartedAtBeforeCreatedAtThrows(): void
    {
        $this->expectException(InvalidTaskDates::class);

        TaskDates::fromPersistence(
            new \DateTimeImmutable('2026-03-18 10:00:00'),
            new \DateTimeImmutable('2026-03-18 09:59:59'),
            null,
        );
    }

    public function testInvalidUpdatedAtBeforeStartedAtThrows(): void
    {
        $this->expectException(InvalidTaskDates::class);

        TaskDates::fromPersistence(
            new \DateTimeImmutable('2026-03-18 10:00:00'),
            new \DateTimeImmutable('2026-03-18 10:05:00'),
            new \DateTimeImmutable('2026-03-18 10:04:59'),
        );
    }

    public function testMarkStartedTwiceThrows(): void
    {
        $this->expectException(InvalidTaskDates::class);

        TaskDates::create(new \DateTimeImmutable('2026-03-18 10:00:00'))
            ->markStarted(new \DateTimeImmutable('2026-03-18 10:05:00'))
            ->markStarted(new \DateTimeImmutable('2026-03-18 10:06:00'));
    }

    public function testRestartClearsStartedAt(): void
    {
        $createdAt = new \DateTimeImmutable('2026-03-18 10:00:00');
        $startedAt = new \DateTimeImmutable('2026-03-18 10:05:00');

        $dates = TaskDates::create($createdAt)
            ->markStarted($startedAt)
            ->restart();

        $this->assertSame($createdAt, $dates->createdAt());
        $this->assertNull($dates->startedAt());
        $this->assertNotNull($dates->updatedAt());
    }

    public function testInvalidUpdatedAtBeforeCreatedAtWithoutStartedAtThrows(): void
    {
        $this->expectException(InvalidTaskDates::class);

        TaskDates::fromPersistence(
            new \DateTimeImmutable('2026-03-18 10:00:00'),
            null,
            new \DateTimeImmutable('2026-03-18 09:59:59'),
        );
    }
}

