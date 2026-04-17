<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\TariffMaxWidth;
use PHPUnit\Framework\TestCase;

/**
 * Tests TariffMaxWidth — максимальная ширина видео в пикселях; должна быть > 0.
 */
final class TariffMaxWidthTest extends TestCase
{
    /** Стандартное значение 1920 принимается и возвращается. */
    public function testCreatesValidWidth(): void
    {
        $vo = new TariffMaxWidth(1920);

        $this->assertSame(1920, $vo->value());
    }

    /** Ноль вызывает DomainException. */
    public function testThrowsOnZero(): void
    {
        $this->expectException(\DomainException::class);

        new TariffMaxWidth(0);
    }

    /** Отрицательное значение вызывает DomainException. */
    public function testThrowsOnNegative(): void
    {
        $this->expectException(\DomainException::class);

        new TariffMaxWidth(-1);
    }

    /** Два объекта с одинаковым значением равны; с разными — нет. */
    public function testEquals(): void
    {
        $a = new TariffMaxWidth(1280);
        $b = new TariffMaxWidth(1280);
        $c = new TariffMaxWidth(1920);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
