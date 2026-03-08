<?php

namespace App\Domain\User\Entity;

class User
{
    private ?int $id;
    private string $email;
    private array $roles;
    private ?string $password = null;
    private ?Tarif $tarif = null;

    public function __construct(
        string $email,
        array $roles,
        ?string $password = null,
        ?Tarif $tarif = null,
        ?int $id = null,
    ) {
        $this->id = $id;

        // TODO через бизнес-логику
        $this->email = $email;
        $this->roles = $roles;
        $this->password = $password;
        $this->tarif = $tarif;
    }

    public function id(): ?int
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

    public function tarif(): ?Tarif
    {
        return $this->tarif;
    }

    public function updateTarif(?Tarif $tarif): void
    {
        $this->tarif = $tarif;
    }

    public function __toString(): string
    {
        return $this->email;
    }
}
