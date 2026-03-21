<?php

namespace App\Domain\User\Entity;

use Symfony\Component\Uid\UuidV4 as Uuid;

class User
{
    private ?Uuid $id;
    private string $email;
    private array $roles;
    private ?string $password = null;
    private ?Tariff $tariff = null;

    public function __construct(
        string  $email,
        array $roles,
        ?string $password = null,
        ?Tariff $tariff = null,
        ?Uuid $id = null,
    ) {
        $this->id = $id;

        // TODO через бизнес-логику
        $this->email = $email;
        $this->roles = $roles;
        $this->password = $password;
        $this->tariff = $tariff;
    }

    public function id(): ?Uuid
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function roles(): array
    {
        return $this->roles;
    }

    public function password(): ?string
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

    public function __toString(): string
    {
        return $this->email;
    }
}
