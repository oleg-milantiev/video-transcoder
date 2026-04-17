<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\TariffInstance;
use PHPUnit\Framework\TestCase;

/**
 * Tests TariffInstance — максимальное число параллельных задач; ≥ 1 допустимо.
 */
final class TariffInstanceTest extends TestCase
{
    /** Минимально допустимое значение 1 принимается. */
    public function testCreatesValidInstanceCount(): void
    {
        $instance = new TariffInstance(1);

        $this->assertSame(1, $instance->value());
    }

    /** Ноль вызывает DomainException — минимум 1 параллельная задача. */
    public function testThrowsOnZeroOrNegativeInstanceCount(): void
    {
        $this->expectException(\DomainException::class);

        new TariffInstance(0);
    }

    /** Два объекта с одинаковым значением равны; с разными — нет. */
    public function testEquals(): void
    {
        $a = new TariffInstance(3);
        $b = new TariffInstance(3);
        $c = new TariffInstance(5);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
