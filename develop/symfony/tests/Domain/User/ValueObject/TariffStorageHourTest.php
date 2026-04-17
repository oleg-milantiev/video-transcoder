<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\TariffStorageHour;
use PHPUnit\Framework\TestCase;

/**
 * Tests TariffStorageHour — время хранения видео в часах; должно быть > 0.
 */
final class TariffStorageHourTest extends TestCase
{
    /** Стандартное значение 24 принимается и возвращается. */
    public function testCreatesValidHour(): void
    {
        $vo = new TariffStorageHour(24);

        $this->assertSame(24, $vo->value());
    }

    /** Нуль вызывает DomainException. */
    public function testThrowsOnZero(): void
    {
        $this->expectException(\DomainException::class);

        new TariffStorageHour(0);
    }

    /** Отрицательное значение вызывает DomainException. */
    public function testThrowsOnNegative(): void
    {
        $this->expectException(\DomainException::class);

        new TariffStorageHour(-1);
    }

    /** Два объекта с одинаковым значением равны; с разными — нет. */
    public function testEquals(): void
    {
        $a = new TariffStorageHour(12);
        $b = new TariffStorageHour(12);
        $c = new TariffStorageHour(24);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
