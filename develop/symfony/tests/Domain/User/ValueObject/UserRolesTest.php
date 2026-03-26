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
}
