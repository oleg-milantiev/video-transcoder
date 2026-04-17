<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\TariffStorageGb;
use PHPUnit\Framework\TestCase;

/**
 * Tests TariffStorageGb — квота хранилища в гигабайтах; должна быть > 0.
 */
final class TariffStorageGbTest extends TestCase
{
    /** Положительное значение принимается и возвращается. */
    public function testCreatesValidStorage(): void
    {
        $vo = new TariffStorageGb(100.0);

        $this->assertSame(100.0, $vo->value());
    }

    /** Нуль вызывает DomainException. */
    public function testThrowsOnZero(): void
    {
        $this->expectException(\DomainException::class);

        new TariffStorageGb(0.0);
    }

    /** Отрицательное значение вызывает DomainException. */
    public function testThrowsOnNegative(): void
    {
        $this->expectException(\DomainException::class);

        new TariffStorageGb(-1.0);
    }

    /** Два объекта с одинаковым значением равны; с разными — нет. */
    public function testEquals(): void
    {
        $a = new TariffStorageGb(50.0);
        $b = new TariffStorageGb(50.0);
        $c = new TariffStorageGb(100.0);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
