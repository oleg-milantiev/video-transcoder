<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\UserEmail;
use PHPUnit\Framework\TestCase;

/**
 * Tests UserEmail — нормализация email (lowercase + trim), валидация формата и длины, equals().
 */
final class UserEmailTest extends TestCase
{
    /** Email нормализуется (lowercase + trim) и возвращается через value() и __toString(). */
    public function testNormalizesEmail(): void
    {
        $email = new UserEmail('  John.Doe@Example.COM  ');

        $this->assertSame('john.doe@example.com', $email->value());
        $this->assertSame('john.doe@example.com', (string) $email);
    }

    /** Некорректный формат вызывает DomainException. */
    public function testThrowsOnInvalidEmail(): void
    {
        $this->expectException(\DomainException::class);

        new UserEmail('not-an-email');
    }

    /** Пустая (пробельная) строка вызывает DomainException. */
    public function testThrowsOnEmptyEmail(): void
    {
        $this->expectException(\DomainException::class);

        new UserEmail('   ');
    }

    /** Email длиннее максимально допустимой длины вызывает DomainException. */
    public function testThrowsOnTooLongEmail(): void
    {
        $this->expectException(\DomainException::class);

        // 175 chars local part + '@b.com' = 181 chars > MAX_LENGTH(180)
        new UserEmail(str_repeat('a', 175) . '@b.com');
    }

    /** Два объекта с одинаковым значением равны; с разными — нет. */
    public function testEquals(): void
    {
        $a = new UserEmail('user@example.com');
        $b = new UserEmail('user@example.com');
        $c = new UserEmail('other@example.com');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
