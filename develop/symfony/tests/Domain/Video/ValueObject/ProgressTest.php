<?php

declare(strict_types=1);

namespace Tests\Domain\Video\ValueObject;

use App\Domain\Video\Exception\InvalidProgress;
use App\Domain\Video\ValueObject\Progress;
use PHPUnit\Framework\TestCase;

class ProgressTest extends TestCase
{
    public function testValidProgress(): void
    {
        $progress = new Progress(50);
        $this->assertSame(50, $progress->value());
        $this->assertFalse($progress->isComplete());
    }

    public function testComplete(): void
    {
        $progress = new Progress(100);
        $this->assertTrue($progress->isComplete());
    }

    public function testNegativeThrows(): void
    {
        $this->expectException(InvalidProgress::class);
        new Progress(-1);
    }

    public function testOver100Throws(): void
    {
        $this->expectException(InvalidProgress::class);
        new Progress(101);
    }
}

