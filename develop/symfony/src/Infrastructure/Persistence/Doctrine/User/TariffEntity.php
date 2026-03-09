<?php

namespace App\Infrastructure\Persistence\Doctrine\User;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TariffRepository::class)]
#[ORM\Table(name: 'tariff')]
class TariffEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 255)]
    public string $title;

    #[ORM\Column]
    public int $delay;

    #[ORM\Column]
    public int $instance;

    public function __toString(): string
    {
        return $this->title;
    }
}
