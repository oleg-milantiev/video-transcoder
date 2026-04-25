<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\Entity;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Entity\Tariff;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\PasswordHash;
use App\Domain\User\ValueObject\TariffDelay;
use App\Domain\User\ValueObject\TariffInstance;
use App\Domain\User\ValueObject\TariffMaxHeight;
use App\Domain\User\ValueObject\TariffMaxWidth;
use App\Domain\User\ValueObject\TariffStorageGb;
use App\Domain\User\ValueObject\TariffStorageHour;
use App\Domain\User\ValueObject\TariffTitle;
use App\Domain\User\ValueObject\TariffVideoDuration;
use App\Domain\User\ValueObject\TariffVideoSize;
use App\Domain\User\ValueObject\UserCreatedAt;
use App\Domain\User\ValueObject\UserEmail;
use App\Domain\User\ValueObject\UserLoginedAt;
use App\Domain\User\ValueObject\UserProfile;
use App\Domain\User\ValueObject\UserRoles;
use PHPUnit\Framework\TestCase;

/**
 * Tests User entity — конструктор, геттеры, мутации email/roles/tariff/password/loginedAt.
 */
final class UserTest extends TestCase
{
    /** Пользователь создаётся с email, ролями и паролем; hasRole() работает корректно. */
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

    /** setPassword() с строкой оборачивает её в PasswordHash; null сбрасывает пароль. */
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

    /** id(), passwordHash() и tariff() возвращают переданные значения. */
    public function testIdAndPasswordHashAndTariffAccessors(): void
    {
        $id = Uuid::fromString('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
        $hash = new PasswordHash('bcrypt-hash');
        $tariff = $this->makeTariff('Pro');

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

    /** updateTariff() заменяет текущий тариф; null разрешается. */
    public function testUpdateTariffReplacesCurrentTariff(): void
    {
        $user = new User(
            email: new UserEmail('user@example.com'),
            roles: new UserRoles(['ROLE_USER']),
        );

        $this->assertNull($user->tariff());

        $tariff = $this->makeTariff('Pro');
        $user->updateTariff($tariff);
        $this->assertSame($tariff, $user->tariff());

        $user->updateTariff(null);
        $this->assertNull($user->tariff());
    }

    /** changeEmail() обновляет email пользователя. */
    public function testChangeEmailUpdatesEmail(): void
    {
        $user = new User(
            email: new UserEmail('old@example.com'),
            roles: new UserRoles(['ROLE_USER']),
        );

        $user->changeEmail(new UserEmail('new@example.com'));

        $this->assertSame('new@example.com', $user->email()->value());
    }

    /** replaceRoles() полностью заменяет набор ролей. */
    public function testReplaceRolesUpdatesRoles(): void
    {
        $user = new User(
            email: new UserEmail('user@example.com'),
            roles: new UserRoles(['ROLE_USER']),
        );

        $user->replaceRoles(new UserRoles(['ROLE_ADMIN']));

        $this->assertSame(['ROLE_ADMIN'], $user->roles()->values());
    }

    /** __toString() возвращает email пользователя. */
    public function testToStringReturnsEmail(): void
    {
        $user = new User(
            email: new UserEmail('display@example.com'),
            roles: new UserRoles(['ROLE_USER']),
        );

        $this->assertSame('display@example.com', (string) $user);
    }

    /** createdAt автоматически устанавливается в момент конструирования. */
    public function testCreatedAtIsAutoSetOnConstruction(): void
    {
        $before = new \DateTimeImmutable();
        $user = new User(
            email: new UserEmail('user@example.com'),
            roles: new UserRoles(['ROLE_USER']),
        );
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $user->createdAt()->value());
        $this->assertLessThanOrEqual($after, $user->createdAt()->value());
    }

    /** Явно переданный createdAt сохраняется без изменений. */
    public function testCreatedAtCanBeProvidedExplicitly(): void
    {
        $dt = new \DateTimeImmutable('2024-01-01 00:00:00');
        $user = new User(
            email: new UserEmail('user@example.com'),
            roles: new UserRoles(['ROLE_USER']),
            createdAt: new UserCreatedAt($dt),
        );

        $this->assertSame($dt, $user->createdAt()->value());
    }

    /** loginedAt() по умолчанию равен null. */
    public function testLoginedAtIsNullByDefault(): void
    {
        $user = new User(
            email: new UserEmail('user@example.com'),
            roles: new UserRoles(['ROLE_USER']),
        );

        $this->assertNull($user->loginedAt());
    }

    /** updateLoginedAt() сохраняет дату последнего входа. */
    public function testUpdateLoginedAt(): void
    {
        $user = new User(
            email: new UserEmail('user@example.com'),
            roles: new UserRoles(['ROLE_USER']),
        );

        $dt = new \DateTimeImmutable('2025-06-15 12:00:00');
        $user->updateLoginedAt(new UserLoginedAt($dt));

        $this->assertNotNull($user->loginedAt());
        $this->assertSame($dt, $user->loginedAt()->value());
    }

    /** loginedAt, переданный в конструктор, сохраняется без изменений. */
    public function testLoginedAtCanBeProvidedInConstructor(): void
    {
        $dt = new \DateTimeImmutable('2025-06-15 12:00:00');
        $user = new User(
            email: new UserEmail('user@example.com'),
            roles: new UserRoles(['ROLE_USER']),
            loginedAt: new UserLoginedAt($dt),
        );

        $this->assertNotNull($user->loginedAt());
        $this->assertSame($dt, $user->loginedAt()->value());
    }

    /** profile() по умолчанию — UserProfile::empty(). */
    public function testProfileIsEmptyByDefault(): void
    {
        $user = new User(
            email: new UserEmail('user@example.com'),
            roles: new UserRoles(['ROLE_USER']),
        );

        $this->assertSame([], $user->profile()->paymentHistory);
        $this->assertSame(0,  $user->profile()->videoCountActive);
        $this->assertNull($user->profile()->paidAt);
        $this->assertNull($user->profile()->paidUntil);
    }

    /** Явно переданный профиль сохраняется в конструкторе. */
    public function testProfileCanBeProvidedInConstructor(): void
    {
        $profile = UserProfile::create(
            videoCountActive:    3,
            videoCountTotal:     10,
            taskCountActive:     1,
            taskCountTotal:      50,
            taskCountByStatus:   [],
            storageUsedBytes:    1024,
            storageDelete24Bytes: 0,
            willStartAt:         'in 5 minutes',
        );

        $user = new User(
            email: new UserEmail('user@example.com'),
            roles: new UserRoles(['ROLE_USER']),
            profile: $profile,
        );

        $this->assertSame(3,    $user->profile()->videoCountActive);
        $this->assertSame(1024, $user->profile()->storageUsedBytes);
    }

    /** updateProfile() заменяет профиль на новый. */
    public function testUpdateProfileReplacesData(): void
    {
        $user = new User(
            email: new UserEmail('user@example.com'),
            roles: new UserRoles(['ROLE_USER']),
        );

        $newProfile = UserProfile::create(
            videoCountActive:    5,
            videoCountTotal:     5,
            taskCountActive:     0,
            taskCountTotal:      10,
            taskCountByStatus:   [],
            storageUsedBytes:    2048,
            storageDelete24Bytes: 512,
            willStartAt:         '',
        );

        $user->updateProfile($newProfile);

        $this->assertSame(5,    $user->profile()->videoCountActive);
        $this->assertSame(2048, $user->profile()->storageUsedBytes);
    }

    /** updateProfile() с UserProfile::empty() сбрасывает профиль. */
    public function testUpdateProfileWithEmptyResetsData(): void
    {
        $user = new User(
            email: new UserEmail('user@example.com'),
            roles: new UserRoles(['ROLE_USER']),
            profile: UserProfile::create(5, 5, 0, 10, [], 2048, 0, ''),
        );

        $user->updateProfile(UserProfile::empty());

        $this->assertSame(0, $user->profile()->videoCountActive);
        $this->assertSame(0, $user->profile()->storageUsedBytes);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function makeTariff(string $title): Tariff
    {
        return new Tariff(
            new TariffTitle($title),
            new TariffDelay(30),
            new TariffInstance(2),
            new TariffVideoDuration(1800),
            new TariffVideoSize(250.0),
            new TariffMaxWidth(1280),
            new TariffMaxHeight(720),
            new TariffStorageGb(50.0),
            new TariffStorageHour(12),
        );
    }
}
