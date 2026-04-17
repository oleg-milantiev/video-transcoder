<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\UserRoles;
use PHPUnit\Framework\TestCase;

/**
 * Tests UserRoles — нормализация, дедупликация и валидация ролей; has() и equals().
 */
final class UserRolesTest extends TestCase
{
    /** Роли нормализуются в uppercase, дедуплицируются, has() регистронезависим. */
    public function testNormalizesAndDeduplicatesRoles(): void
    {
        $roles = new UserRoles([' role_admin ', 'ROLE_ADMIN', 'role_user']);

        $this->assertSame(['ROLE_ADMIN', 'ROLE_USER'], $roles->values());
        $this->assertTrue($roles->has('role_user'));
    }

    /** Пустой массив ролей вызывает DomainException. */
    public function testThrowsOnEmptyRoleList(): void
    {
        $this->expectException(\DomainException::class);

        new UserRoles([]);
    }

    /** Роль без префикса ROLE_ вызывает DomainException. */
    public function testThrowsOnInvalidRoleFormat(): void
    {
        $this->expectException(\DomainException::class);

        new UserRoles(['ADMIN']);
    }

    /** Роль из одних пробелов вызывает DomainException. */
    public function testThrowsOnEmptyStringRole(): void
    {
        $this->expectException(\DomainException::class);

        new UserRoles(['  ']);
    }

    /** Два объекта с одинаковым набором ролей равны; с разным — нет. */
    public function testEquals(): void
    {
        $a = new UserRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $b = new UserRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $c = new UserRoles(['ROLE_USER']);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
