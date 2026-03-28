<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\UserRoles;
use PHPUnit\Framework\TestCase;

final class UserRolesTest extends TestCase
{
    public function testNormalizesAndDeduplicatesRoles(): void
    {
        $roles = new UserRoles([' role_admin ', 'ROLE_ADMIN', 'role_user']);

        $this->assertSame(['ROLE_ADMIN', 'ROLE_USER'], $roles->values());
        $this->assertTrue($roles->has('role_user'));
    }

    public function testThrowsOnEmptyRoleList(): void
    {
        $this->expectException(\DomainException::class);

        new UserRoles([]);
    }

    public function testThrowsOnInvalidRoleFormat(): void
    {
        $this->expectException(\DomainException::class);

        new UserRoles(['ADMIN']);
    }

    public function testThrowsOnEmptyStringRole(): void
    {
        $this->expectException(\DomainException::class);

        new UserRoles(['  ']);
    }

    public function testEquals(): void
    {
        $a = new UserRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $b = new UserRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $c = new UserRoles(['ROLE_USER']);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
