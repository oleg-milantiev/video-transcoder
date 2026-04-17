<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\TariffDelay;
use PHPUnit\Framework\TestCase;

/**
 * Tests TariffDelay — задержка между задачами в секундах; 0 допустим, отрицательные — нет.
 */
final class TariffDelayTest extends TestCase
{
    /** Нулевая задержка (без ограничений) допустима. */
    public function testCreatesValidDelay(): void
    {
        $delay = new TariffDelay(0);

        $this->assertSame(0, $delay->value());
    }

    /** Отрицательная задержка вызывает DomainException. */
    public function testThrowsOnNegativeDelay(): void
    {
        $this->expectException(\DomainException::class);

        new TariffDelay(-1);
    }

    /** Два объекта с одинаковым значением равны; с разными — нет. */
    public function testEquals(): void
    {
        $a = new TariffDelay(30);
        $b = new TariffDelay(30);
        $c = new TariffDelay(60);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
