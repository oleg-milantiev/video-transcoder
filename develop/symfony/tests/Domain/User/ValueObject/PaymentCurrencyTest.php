<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\PaymentCurrency;
use PHPUnit\Framework\TestCase;

/**
 * Tests PaymentCurrency VO — нормализация, валидация, equals, __toString.
 */
final class PaymentCurrencyTest extends TestCase
{
    /** Валидные коды нормализуются к верхнему регистру. */
    public function testNormalizesToUppercase(): void
    {
        $this->assertSame('USD', (new PaymentCurrency('usd'))->value());
        $this->assertSame('EUR', (new PaymentCurrency(' EUR '))->value());
        $this->assertSame('RUB', (new PaymentCurrency('rub'))->value());
    }

    /** __toString() возвращает код валюты. */
    public function testToStringReturnsCurrencyCode(): void
    {
        $this->assertSame('USD', (string) new PaymentCurrency('USD'));
    }

    /** equals() сравнивает коды без учёта регистра. */
    public function testEquals(): void
    {
        $a = new PaymentCurrency('USD');
        $b = new PaymentCurrency('usd');
        $c = new PaymentCurrency('EUR');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    /** Пустая строка выбрасывает DomainException. */
    public function testEmptyThrows(): void
    {
        $this->expectException(\DomainException::class);
        new PaymentCurrency('');
    }

    /** Код длиннее 3 символов выбрасывает DomainException. */
    public function testTooLongThrows(): void
    {
        $this->expectException(\DomainException::class);
        new PaymentCurrency('EURO');
    }

    /** Код короче 3 символов выбрасывает DomainException. */
    public function testTooShortThrows(): void
    {
        $this->expectException(\DomainException::class);
        new PaymentCurrency('US');
    }

    /** Цифры в коде выбрасывают DomainException. */
    public function testDigitsThrow(): void
    {
        $this->expectException(\DomainException::class);
        new PaymentCurrency('U5D');
    }
}
