<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\UserEmail;
use PHPUnit\Framework\TestCase;

final class UserEmailTest extends TestCase
{
    public function testNormalizesEmail(): void
    {
        $email = new UserEmail('  John.Doe@Example.COM  ');

        $this->assertSame('john.doe@example.com', $email->value());
        $this->assertSame('john.doe@example.com', (string) $email);
    }

    public function testThrowsOnInvalidEmail(): void
    {
        $this->expectException(\DomainException::class);

        new UserEmail('not-an-email');
    }

    public function testThrowsOnEmptyEmail(): void
    {
        $this->expectException(\DomainException::class);

        new UserEmail('   ');
    }

    public function testThrowsOnTooLongEmail(): void
    {
        $this->expectException(\DomainException::class);

        // 175 chars local part + '@b.com' = 181 chars > MAX_LENGTH(180)
        new UserEmail(str_repeat('a', 175) . '@b.com');
    }

    public function testEquals(): void
    {
        $a = new UserEmail('user@example.com');
        $b = new UserEmail('user@example.com');
        $c = new UserEmail('other@example.com');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
