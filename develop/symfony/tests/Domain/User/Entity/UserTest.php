<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\Entity;

use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\PasswordHash;
use App\Domain\User\ValueObject\UserEmail;
use App\Domain\User\ValueObject\UserRoles;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testConstructsWithValueObjects(): void
    {
        $user = new User(
            email: new UserEmail('admin@example.com'),
            roles: new UserRoles(['ROLE_ADMIN']),
            password: new PasswordHash('hash-value')
        );

        $this->assertSame('admin@example.com', $user->email()->value());
        $this->assertSame(['ROLE_ADMIN'], $user->roles()->values());
        $this->assertSame('hash-value', $user->password());
        $this->assertTrue($user->hasRole('ROLE_ADMIN'));
    }

    public function testCanUpdatePasswordFromInfrastructureString(): void
    {
        $user = new User(
            email: new UserEmail('admin@example.com'),
            roles: new UserRoles(['ROLE_ADMIN'])
        );

        $user->setPassword('new-hash-value');
        $this->assertSame('new-hash-value', $user->password());

        $user->setPassword(null);
        $this->assertNull($user->password());
    }
}
