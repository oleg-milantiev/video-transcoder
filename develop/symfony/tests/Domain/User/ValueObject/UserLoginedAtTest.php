<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\UserLoginedAt;
use PHPUnit\Framework\TestCase;

/**
 * Tests UserLoginedAt — обёртка над DateTimeImmutable для даты последнего входа; equals() и __toString().
 */
final class UserLoginedAtTest extends TestCase
{
    /** DateTimeImmutable сохраняется и возвращается через value(). */
    public function testWrapsDateTimeImmutable(): void
    {
        $dt = new \DateTimeImmutable('2025-03-20 18:00:00');
        $vo = new UserLoginedAt($dt);

        $this->assertSame($dt, $vo->value());
    }

    /** __toString() форматирует дату в формате ATOM. */
    public function testToStringFormatsAsAtom(): void
    {
        $dt = new \DateTimeImmutable('2025-03-20T18:00:00+00:00');
        $vo = new UserLoginedAt($dt);

        $this->assertSame($dt->format(\DateTimeInterface::ATOM), (string) $vo);
    }

    /** Два объекта с идентичной датой равны. */
    public function testEqualsReturnsTrueForSameDateTime(): void
    {
        $a = new UserLoginedAt(new \DateTimeImmutable('2025-03-20 18:00:00'));
        $b = new UserLoginedAt(new \DateTimeImmutable('2025-03-20 18:00:00'));

        $this->assertTrue($a->equals($b));
    }

    /** Два объекта с разными датами не равны. */
    public function testEqualsReturnsFalseForDifferentDateTime(): void
    {
        $a = new UserLoginedAt(new \DateTimeImmutable('2025-03-20 18:00:00'));
        $b = new UserLoginedAt(new \DateTimeImmutable('2025-03-21 18:00:00'));

        $this->assertFalse($a->equals($b));
    }
}
