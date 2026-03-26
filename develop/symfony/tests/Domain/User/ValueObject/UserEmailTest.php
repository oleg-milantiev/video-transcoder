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
}
