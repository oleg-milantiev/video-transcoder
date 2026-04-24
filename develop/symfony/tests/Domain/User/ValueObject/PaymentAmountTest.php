<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\PaymentAmount;
use PHPUnit\Framework\TestCase;

/**
 * Tests PaymentAmount VO — нулевое значение, положительные суммы, отрицательные значения, конвертация.
 */
final class PaymentAmountTest extends TestCase
{
    /** Нулевая сумма допустима. */
    public function testZeroIsValid(): void
    {
        $amount = new PaymentAmount(0);
        $this->assertSame(0, $amount->value());
    }

    /** Положительные суммы сохраняются без изменений. */
    public function testPositiveValue(): void
    {
        $amount = new PaymentAmount(9900);
        $this->assertSame(9900, $amount->value());
    }

    /** Отрицательная сумма выбрасывает DomainException. */
    public function testNegativeThrows(): void
    {
        $this->expectException(\DomainException::class);
        new PaymentAmount(-1);
    }

    /** toMajorUnits() с делением на 100 (центы → доллары). */
    public function testToMajorUnits(): void
    {
        $amount = new PaymentAmount(9999);
        $this->assertEqualsWithDelta(99.99, $amount->toMajorUnits(100), 0.001);
    }

    /** toMajorUnits() с делением на 1 (если валюта без дробных единиц). */
    public function testToMajorUnitsWithFractionOne(): void
    {
        $amount = new PaymentAmount(500);
        $this->assertEqualsWithDelta(500.0, $amount->toMajorUnits(1), 0.001);
    }

    /** toMajorUnits() с нулевым дробным делителем выбрасывает DomainException. */
    public function testToMajorUnitsZeroFractionThrows(): void
    {
        $amount = new PaymentAmount(100);
        $this->expectException(\DomainException::class);
        $amount->toMajorUnits(0);
    }

    /** equals() сравнивает значения. */
    public function testEquals(): void
    {
        $a = new PaymentAmount(500);
        $b = new PaymentAmount(500);
        $c = new PaymentAmount(100);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    /** __toString() возвращает строку с числом центов. */
    public function testToString(): void
    {
        $this->assertSame('1200', (string) new PaymentAmount(1200));
    }
}
