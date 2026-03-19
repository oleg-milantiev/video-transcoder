<?php

declare(strict_types=1);

namespace App\Tests\Domain\Video\ValueObject;

use App\Domain\Video\Exception\InvalidVideoDates;
use App\Domain\Video\ValueObject\VideoDates;
use PHPUnit\Framework\TestCase;

final class VideoDatesTest extends TestCase
{
    public function testCreateInitializesOnlyCreatedAt(): void
    {
        $createdAt = new \DateTimeImmutable('2026-03-19 10:00:00');

        $dates = VideoDates::create($createdAt);

        $this->assertSame($createdAt, $dates->createdAt());
        $this->assertNull($dates->updatedAt());
    }

    public function testTouchSetsUpdatedAt(): void
    {
        $createdAt = new \DateTimeImmutable('2026-03-19 10:00:00');
        $updatedAt = new \DateTimeImmutable('2026-03-19 10:05:00');

        $dates = VideoDates::create($createdAt)->touch($updatedAt);

        $this->assertSame($createdAt, $dates->createdAt());
        $this->assertSame($updatedAt, $dates->updatedAt());
    }

    public function testInvalidUpdatedAtBeforeCreatedAtThrows(): void
    {
        $this->expectException(InvalidVideoDates::class);

        VideoDates::fromPersistence(
            new \DateTimeImmutable('2026-03-19 10:00:00'),
            new \DateTimeImmutable('2026-03-19 09:59:59'),
        );
    }
}

