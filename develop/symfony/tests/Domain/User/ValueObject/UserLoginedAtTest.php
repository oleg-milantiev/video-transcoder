<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\UserLoginedAt;
use PHPUnit\Framework\TestCase;

final class UserLoginedAtTest extends TestCase
{
    public function testWrapsDateTimeImmutable(): void
    {
        $dt = new \DateTimeImmutable('2025-03-20 18:00:00');
        $vo = new UserLoginedAt($dt);

        $this->assertSame($dt, $vo->value());
    }

    public function testToStringFormatsAsAtom(): void
    {
        $dt = new \DateTimeImmutable('2025-03-20T18:00:00+00:00');
        $vo = new UserLoginedAt($dt);

        $this->assertSame($dt->format(\DateTimeInterface::ATOM), (string) $vo);
    }

    public function testEqualsReturnsTrueForSameDateTime(): void
    {
        $a = new UserLoginedAt(new \DateTimeImmutable('2025-03-20 18:00:00'));
        $b = new UserLoginedAt(new \DateTimeImmutable('2025-03-20 18:00:00'));

        $this->assertTrue($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentDateTime(): void
    {
        $a = new UserLoginedAt(new \DateTimeImmutable('2025-03-20 18:00:00'));
        $b = new UserLoginedAt(new \DateTimeImmutable('2025-03-21 18:00:00'));

        $this->assertFalse($a->equals($b));
    }
}
