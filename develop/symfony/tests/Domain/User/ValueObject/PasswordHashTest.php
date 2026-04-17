<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\PasswordHash;
use PHPUnit\Framework\TestCase;

/**
 * Tests PasswordHash — хранение хэша пароля, equals() и защита от пустого значения.
 */
final class PasswordHashTest extends TestCase
{
    /** Валидный хэш сохраняется и возвращается через value(). */
    public function testCreatesValidHash(): void
    {
        $hash = new PasswordHash('$2y$13$J5Ca1kRANfFfQY8XrVcx7udm6vY1X6WPrlQmOQ6xVv2mO5H1W0WQm');

        $this->assertSame('$2y$13$J5Ca1kRANfFfQY8XrVcx7udm6vY1X6WPrlQmOQ6xVv2mO5H1W0WQm', $hash->value());
    }

    /** Пустая (пробельная) строка вызывает DomainException. */
    public function testThrowsOnEmptyHash(): void
    {
        $this->expectException(\DomainException::class);

        new PasswordHash('   ');
    }

    /** Два хэша с одинаковым значением равны; с разными — нет. */
    public function testEquals(): void
    {
        $a = new PasswordHash('hash-abc');
        $b = new PasswordHash('hash-abc');
        $c = new PasswordHash('hash-xyz');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
