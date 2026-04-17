<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\TariffMaxHeight;
use PHPUnit\Framework\TestCase;

/**
 * Tests TariffMaxHeight — максимальная высота видео в пикселях; должна быть > 0.
 */
final class TariffMaxHeightTest extends TestCase
{
    /** Стандартное значение 1080 принимается и возвращается. */
    public function testCreatesValidHeight(): void
    {
        $vo = new TariffMaxHeight(1080);

        $this->assertSame(1080, $vo->value());
    }

    /** Ноль вызывает DomainException. */
    public function testThrowsOnZero(): void
    {
        $this->expectException(\DomainException::class);

        new TariffMaxHeight(0);
    }

    /** Отрицательное значение вызывает DomainException. */
    public function testThrowsOnNegative(): void
    {
        $this->expectException(\DomainException::class);

        new TariffMaxHeight(-1);
    }

    /** Два объекта с одинаковым значением равны; с разными — нет. */
    public function testEquals(): void
    {
        $a = new TariffMaxHeight(720);
        $b = new TariffMaxHeight(720);
        $c = new TariffMaxHeight(1080);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
