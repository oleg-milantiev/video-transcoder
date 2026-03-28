<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\Entity;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Entity\Tariff;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\PasswordHash;
use App\Domain\User\ValueObject\TariffDelay;
use App\Domain\User\ValueObject\TariffInstance;
use App\Domain\User\ValueObject\TariffTitle;
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

    public function testIdAndPasswordHashAndTariffAccessors(): void
    {
        $id = Uuid::fromString('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
        $hash = new PasswordHash('bcrypt-hash');
        $tariff = new Tariff(new TariffTitle('Pro'), new TariffDelay(60), new TariffInstance(2));

        $user = new User(
            email: new UserEmail('user@example.com'),
            roles: new UserRoles(['ROLE_USER']),
            password: $hash,
            tariff: $tariff,
            id: $id,
        );

        $this->assertSame($id, $user->id());
        $this->assertSame($hash, $user->passwordHash());
        $this->assertSame($tariff, $user->tariff());
    }

    public function testUpdateTariffReplacesCurrentTariff(): void
    {
        $user = new User(
            email: new UserEmail('user@example.com'),
            roles: new UserRoles(['ROLE_USER']),
        );

        $this->assertNull($user->tariff());

        $tariff = new Tariff(new TariffTitle('Pro'), new TariffDelay(30), new TariffInstance(5));
        $user->updateTariff($tariff);

        $this->assertSame($tariff, $user->tariff());

        $user->updateTariff(null);
        $this->assertNull($user->tariff());
    }

    public function testChangeEmailUpdatesEmail(): void
    {
        $user = new User(
            email: new UserEmail('old@example.com'),
            roles: new UserRoles(['ROLE_USER']),
        );

        $user->changeEmail(new UserEmail('new@example.com'));

        $this->assertSame('new@example.com', $user->email()->value());
    }

    public function testReplaceRolesUpdatesRoles(): void
    {
        $user = new User(
            email: new UserEmail('user@example.com'),
            roles: new UserRoles(['ROLE_USER']),
        );

        $user->replaceRoles(new UserRoles(['ROLE_ADMIN']));

        $this->assertSame(['ROLE_ADMIN'], $user->roles()->values());
    }

    public function testToStringReturnsEmail(): void
    {
        $user = new User(
            email: new UserEmail('display@example.com'),
            roles: new UserRoles(['ROLE_USER']),
        );

        $this->assertSame('display@example.com', (string) $user);
    }
}
