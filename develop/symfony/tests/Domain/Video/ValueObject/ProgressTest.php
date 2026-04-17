<?php

declare(strict_types=1);

namespace App\Tests\Domain\Video\ValueObject;

use App\Domain\Video\Exception\InvalidProgress;
use App\Domain\Video\ValueObject\Progress;
use PHPUnit\Framework\TestCase;

/**
 * Tests Progress — прогресс задачи в процентах [0, 100]; isComplete(), граничные значения.
 */
final class ProgressTest extends TestCase
{
    /** Значение 50 допустимо; isComplete() == false. */
    public function testValidProgress(): void
    {
        $progress = new Progress(50);
        $this->assertSame(50, $progress->value());
        $this->assertFalse($progress->isComplete());
    }

    /** Значение 100 считается завершённым. */
    public function testComplete(): void
    {
        $progress = new Progress(100);
        $this->assertTrue($progress->isComplete());
    }

    /** Отрицательное значение бросает InvalidProgress. */
    public function testNegativeThrows(): void
    {
        $this->expectException(InvalidProgress::class);
        new Progress(-1);
    }

    /** Значение больше 100 бросает InvalidProgress. */
    public function testOver100Throws(): void
    {
        $this->expectException(InvalidProgress::class);
        new Progress(101);
    }

    /** Нулевой прогресс допустим и не является завершённым. */
    public function testZeroIsValidAndNotComplete(): void
    {
        $progress = new Progress(0);
        $this->assertSame(0, $progress->value());
        $this->assertFalse($progress->isComplete());
    }

    /** Граничные значения 0 и 100 допустимы. */
    public function testBoundaryValues(): void
    {
        $this->assertSame(0, (new Progress(0))->value());
        $this->assertSame(100, (new Progress(100))->value());
    }
}
