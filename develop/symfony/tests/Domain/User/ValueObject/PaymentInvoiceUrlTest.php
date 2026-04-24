<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\PaymentInvoiceUrl;
use PHPUnit\Framework\TestCase;

/**
 * Tests PaymentInvoiceUrl VO — trim, FILTER_VALIDATE_URL, equals, __toString.
 */
final class PaymentInvoiceUrlTest extends TestCase
{
    /** Валидный https URL сохраняется. */
    public function testValidHttpsUrl(): void
    {
        $url = 'https://pay.stripe.com/invoices/inv_abc123';
        $vo  = new PaymentInvoiceUrl($url);
        $this->assertSame($url, $vo->value());
    }

    /** Пробелы обрезаются. */
    public function testTrimsWhitespace(): void
    {
        $url = 'https://example.com/invoice';
        $vo  = new PaymentInvoiceUrl("  $url  ");
        $this->assertSame($url, $vo->value());
    }

    /** __toString() возвращает URL. */
    public function testToString(): void
    {
        $url = 'https://example.com/receipt';
        $this->assertSame($url, (string) new PaymentInvoiceUrl($url));
    }

    /** equals() сравнивает URL. */
    public function testEquals(): void
    {
        $a = new PaymentInvoiceUrl('https://example.com/a');
        $b = new PaymentInvoiceUrl('https://example.com/a');
        $c = new PaymentInvoiceUrl('https://example.com/b');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    /** Пустая строка выбрасывает DomainException. */
    public function testEmptyThrows(): void
    {
        $this->expectException(\DomainException::class);
        new PaymentInvoiceUrl('');
    }

    /** Невалидный URL выбрасывает DomainException. */
    public function testInvalidUrlThrows(): void
    {
        $this->expectException(\DomainException::class);
        new PaymentInvoiceUrl('not-a-url');
    }

    /** URL длиннее 2048 символов выбрасывает DomainException. */
    public function testTooLongThrows(): void
    {
        $this->expectException(\DomainException::class);
        new PaymentInvoiceUrl('https://example.com/' . str_repeat('x', 2030));
    }
}
