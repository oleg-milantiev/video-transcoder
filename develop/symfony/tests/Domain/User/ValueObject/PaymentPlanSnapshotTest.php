<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\PaymentPlanSnapshot;
use PHPUnit\Framework\TestCase;

/**
 * Tests PaymentPlanSnapshot VO — trim, валидация, equals, __toString.
 */
final class PaymentPlanSnapshotTest extends TestCase
{
    /** Значение сохраняется после trim. */
    public function testTrimsWhitespace(): void
    {
        $vo = new PaymentPlanSnapshot('  Pro Plan  ');
        $this->assertSame('Pro Plan', $vo->value());
    }

    /** __toString() возвращает название плана. */
    public function testToString(): void
    {
        $this->assertSame('Basic', (string) new PaymentPlanSnapshot('Basic'));
    }

    /** equals() сравнивает значения строго. */
    public function testEquals(): void
    {
        $a = new PaymentPlanSnapshot('Pro');
        $b = new PaymentPlanSnapshot('Pro');
        $c = new PaymentPlanSnapshot('Basic');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    /** Пустая строка выбрасывает DomainException. */
    public function testEmptyThrows(): void
    {
        $this->expectException(\DomainException::class);
        new PaymentPlanSnapshot('');
    }

    /** Строка только из пробелов выбрасывает DomainException. */
    public function testWhitespaceOnlyThrows(): void
    {
        $this->expectException(\DomainException::class);
        new PaymentPlanSnapshot('   ');
    }

    /** Строка длиннее 255 символов выбрасывает DomainException. */
    public function testTooLongThrows(): void
    {
        $this->expectException(\DomainException::class);
        new PaymentPlanSnapshot(str_repeat('x', 256));
    }

    /** Ровно 255 символов — допустимо. */
    public function testExactMaxLengthIsValid(): void
    {
        $vo = new PaymentPlanSnapshot(str_repeat('x', 255));
        $this->assertSame(255, mb_strlen($vo->value()));
    }
}
