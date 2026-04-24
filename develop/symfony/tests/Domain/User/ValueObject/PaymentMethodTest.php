<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\PaymentMethod;
use PHPUnit\Framework\TestCase;

/**
 * Tests PaymentMethod VO — trim, валидация, equals, __toString.
 */
final class PaymentMethodTest extends TestCase
{
    /** Пробелы обрезаются. */
    public function testTrimsWhitespace(): void
    {
        $vo = new PaymentMethod('  card  ');
        $this->assertSame('card', $vo->value());
    }

    /** Типичные значения допустимы. */
    public function testCommonValues(): void
    {
        $this->assertSame('card',          (new PaymentMethod('card'))->value());
        $this->assertSame('bank_transfer', (new PaymentMethod('bank_transfer'))->value());
        $this->assertSame('wallet',        (new PaymentMethod('wallet'))->value());
    }

    /** __toString() возвращает значение. */
    public function testToString(): void
    {
        $this->assertSame('card', (string) new PaymentMethod('card'));
    }

    /** equals() сравнивает значения. */
    public function testEquals(): void
    {
        $a = new PaymentMethod('card');
        $b = new PaymentMethod('card');
        $c = new PaymentMethod('wallet');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    /** Пустая строка выбрасывает DomainException. */
    public function testEmptyThrows(): void
    {
        $this->expectException(\DomainException::class);
        new PaymentMethod('');
    }

    /** Строка длиннее 100 символов выбрасывает DomainException. */
    public function testTooLongThrows(): void
    {
        $this->expectException(\DomainException::class);
        new PaymentMethod(str_repeat('x', 101));
    }

    /** Ровно 100 символов — допустимо. */
    public function testExactMaxLengthIsValid(): void
    {
        $vo = new PaymentMethod(str_repeat('x', 100));
        $this->assertSame(100, mb_strlen($vo->value()));
    }
}
