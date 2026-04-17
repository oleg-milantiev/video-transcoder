<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\TariffVideoDuration;
use PHPUnit\Framework\TestCase;

/**
 * Tests TariffVideoDuration — максимальная длительность видео в секундах; должна быть > 0.
 */
final class TariffVideoDurationTest extends TestCase
{
    /** Стандартное значение 3600 с принимается и возвращается. */
    public function testCreatesValidDuration(): void
    {
        $vo = new TariffVideoDuration(3600);

        $this->assertSame(3600, $vo->value());
    }

    /** Нуль вызывает DomainException. */
    public function testThrowsOnZero(): void
    {
        $this->expectException(\DomainException::class);

        new TariffVideoDuration(0);
    }

    /** Отрицательное значение вызывает DomainException. */
    public function testThrowsOnNegative(): void
    {
        $this->expectException(\DomainException::class);

        new TariffVideoDuration(-1);
    }

    /** Два объекта с одинаковым значением равны; с разными — нет. */
    public function testEquals(): void
    {
        $a = new TariffVideoDuration(60);
        $b = new TariffVideoDuration(60);
        $c = new TariffVideoDuration(120);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
