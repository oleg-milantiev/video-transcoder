<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\PasswordHash;
use PHPUnit\Framework\TestCase;

final class PasswordHashTest extends TestCase
{
    public function testCreatesValidHash(): void
    {
        $hash = new PasswordHash('$2y$13$J5Ca1kRANfFfQY8XrVcx7udm6vY1X6WPrlQmOQ6xVv2mO5H1W0WQm');

        $this->assertSame('$2y$13$J5Ca1kRANfFfQY8XrVcx7udm6vY1X6WPrlQmOQ6xVv2mO5H1W0WQm', $hash->value());
    }

    public function testThrowsOnEmptyHash(): void
    {
        $this->expectException(\DomainException::class);

        new PasswordHash('   ');
    }

    public function testEquals(): void
    {
        $a = new PasswordHash('hash-abc');
        $b = new PasswordHash('hash-abc');
        $c = new PasswordHash('hash-xyz');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
