<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\PaymentExternalId;
use PHPUnit\Framework\TestCase;

/**
 * Tests PaymentExternalId VO — trim, валидация, equals, __toString.
 */
final class PaymentExternalIdTest extends TestCase
{
    /** Пробелы обрезаются. */
    public function testTrimsWhitespace(): void
    {
        $vo = new PaymentExternalId('  pi_abc123  ');
        $this->assertSame('pi_abc123', $vo->value());
    }

    /** __toString() возвращает значение. */
    public function testToString(): void
    {
        $this->assertSame('pi_abc123', (string) new PaymentExternalId('pi_abc123'));
    }

    /** equals() сравнивает значения. */
    public function testEquals(): void
    {
        $a = new PaymentExternalId('pi_abc');
        $b = new PaymentExternalId('pi_abc');
        $c = new PaymentExternalId('pi_xyz');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    /** Пустая строка выбрасывает DomainException. */
    public function testEmptyThrows(): void
    {
        $this->expectException(\DomainException::class);
        new PaymentExternalId('');
    }

    /** Строка из пробелов выбрасывает DomainException. */
    public function testWhitespaceOnlyThrows(): void
    {
        $this->expectException(\DomainException::class);
        new PaymentExternalId('   ');
    }

    /** Строка длиннее 255 символов выбрасывает DomainException. */
    public function testTooLongThrows(): void
    {
        $this->expectException(\DomainException::class);
        new PaymentExternalId(str_repeat('x', 256));
    }

    /** Ровно 255 символов — допустимо. */
    public function testExactMaxLengthIsValid(): void
    {
        $vo = new PaymentExternalId(str_repeat('x', 255));
        $this->assertSame(255, strlen($vo->value()));
    }
}
