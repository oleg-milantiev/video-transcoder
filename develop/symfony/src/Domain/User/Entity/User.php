<?php
declare(strict_types=1);

namespace App\Domain\User\Entity;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\ValueObject\PasswordHash;
use App\Domain\User\ValueObject\UserCreatedAt;
use App\Domain\User\ValueObject\UserEmail;
use App\Domain\User\ValueObject\UserLoginedAt;
use App\Domain\User\ValueObject\UserRoles;

class User
{
    private ?Uuid $id;
    private UserEmail $email;
    private UserRoles $roles;
    private ?PasswordHash $password;
    private ?Tariff $tariff;
    private UserCreatedAt $createdAt;
    private ?UserLoginedAt $loginedAt;

    public function __construct(
        UserEmail $email,
        UserRoles $roles,
        ?PasswordHash $password = null,
        ?Tariff $tariff = null,
        ?Uuid $id = null,
        ?UserCreatedAt $createdAt = null,
        ?UserLoginedAt $loginedAt = null,
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->roles = $roles;
        $this->password = $password;
        $this->tariff = $tariff;
        $this->createdAt = $createdAt ?? new UserCreatedAt(new \DateTimeImmutable());
        $this->loginedAt = $loginedAt;
    }

    public function id(): ?Uuid
    {
        return $this->id;
    }

    public function email(): UserEmail
    {
        return $this->email;
    }

    public function roles(): UserRoles
    {
        return $this->roles;
    }

    /**
     * Backward-compatible scalar accessor used by infrastructure mapping.
     */
    public function password(): ?string
    {
        return $this->password?->value();
    }

    public function passwordHash(): ?PasswordHash
    {
        return $this->password;
    }

    public function tariff(): ?Tariff
    {
        return $this->tariff;
    }

    public function updateTariff(?Tariff $tariff): void
    {
        $this->tariff = $tariff;
    }

    public function changeEmail(UserEmail $email): void
    {
        $this->email = $email;
    }

    public function replaceRoles(UserRoles $roles): void
    {
        $this->roles = $roles;
    }

    public function setPassword(?string $password): void
    {
        $this->password = $password === null ? null : new PasswordHash($password);
    }

    public function hasRole(string $role): bool
    {
        return $this->roles->has($role);
    }

    public function createdAt(): UserCreatedAt
    {
        return $this->createdAt;
    }

    public function loginedAt(): ?UserLoginedAt
    {
        return $this->loginedAt;
    }

    public function updateLoginedAt(UserLoginedAt $loginedAt): void
    {
        $this->loginedAt = $loginedAt;
    }

    public function __toString(): string
    {
        return $this->email->value();
    }
}
