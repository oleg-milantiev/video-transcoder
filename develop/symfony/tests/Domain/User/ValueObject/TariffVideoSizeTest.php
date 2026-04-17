<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\TariffVideoSize;
use PHPUnit\Framework\TestCase;

/**
 * Tests TariffVideoSize — максимальный размер загружаемого видео в МБ; должен быть > 0.
 */
final class TariffVideoSizeTest extends TestCase
{
    /** Положительное значение принимается и возвращается. */
    public function testCreatesValidSize(): void
    {
        $vo = new TariffVideoSize(500.0);

        $this->assertSame(500.0, $vo->value());
    }

    /** Нуль вызывает DomainException. */
    public function testThrowsOnZero(): void
    {
        $this->expectException(\DomainException::class);

        new TariffVideoSize(0.0);
    }

    /** Отрицательное значение вызывает DomainException. */
    public function testThrowsOnNegative(): void
    {
        $this->expectException(\DomainException::class);

        new TariffVideoSize(-1.0);
    }

    /** Два объекта с одинаковым значением равны; с разными — нет. */
    public function testEquals(): void
    {
        $a = new TariffVideoSize(100.0);
        $b = new TariffVideoSize(100.0);
        $c = new TariffVideoSize(200.0);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
