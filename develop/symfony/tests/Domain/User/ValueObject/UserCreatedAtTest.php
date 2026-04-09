<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\UserCreatedAt;
use PHPUnit\Framework\TestCase;

final class UserCreatedAtTest extends TestCase
{
    public function testWrapsDateTimeImmutable(): void
    {
        $dt = new \DateTimeImmutable('2024-01-15 10:30:00');
        $vo = new UserCreatedAt($dt);

        $this->assertSame($dt, $vo->value());
    }

    public function testToStringFormatsAsAtom(): void
    {
        $dt = new \DateTimeImmutable('2024-01-15T10:30:00+00:00');
        $vo = new UserCreatedAt($dt);

        $this->assertSame($dt->format(\DateTimeInterface::ATOM), (string) $vo);
    }

    public function testEqualsReturnsTrueForSameDateTime(): void
    {
        $dt = new \DateTimeImmutable('2024-06-01 00:00:00');
        $a = new UserCreatedAt($dt);
        $b = new UserCreatedAt(new \DateTimeImmutable('2024-06-01 00:00:00'));

        $this->assertTrue($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentDateTime(): void
    {
        $a = new UserCreatedAt(new \DateTimeImmutable('2024-06-01 00:00:00'));
        $b = new UserCreatedAt(new \DateTimeImmutable('2024-06-02 00:00:00'));

        $this->assertFalse($a->equals($b));
    }
}
