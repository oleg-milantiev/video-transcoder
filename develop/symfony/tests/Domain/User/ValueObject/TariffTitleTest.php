<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\TariffTitle;
use PHPUnit\Framework\TestCase;

/**
 * Tests TariffTitle — название тарифа; trim, ограничение длины, equals().
 */
final class TariffTitleTest extends TestCase
{
    /** Строка обрезается и возвращается; __toString() совпадает с value(). */
    public function testCreatesValidTitle(): void
    {
        $title = new TariffTitle('  Pro Plan  ');

        $this->assertSame('Pro Plan', $title->value());
        $this->assertSame('Pro Plan', (string) $title);
    }

    /** Пустая (пробельная) строка вызывает DomainException. */
    public function testThrowsOnEmptyTitle(): void
    {
        $this->expectException(\DomainException::class);

        new TariffTitle('   ');
    }

    /** Строка длиннее 255 символов вызывает DomainException. */
    public function testThrowsOnTooLongTitle(): void
    {
        $this->expectException(\DomainException::class);

        new TariffTitle(str_repeat('a', 256));
    }

    /** Два объекта с одинаковым значением равны; с разными — нет. */
    public function testEquals(): void
    {
        $a = new TariffTitle('Pro Plan');
        $b = new TariffTitle('Pro Plan');
        $c = new TariffTitle('Free Plan');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
