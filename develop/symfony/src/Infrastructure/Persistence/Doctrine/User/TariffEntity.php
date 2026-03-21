<?php

namespace App\Infrastructure\Persistence\Doctrine\User;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV4 as Uuid;

#[ORM\Entity(repositoryClass: TariffRepository::class)]
#[ORM\Table(name: 'tariff')]
class TariffEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    public ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    public string $title;

    #[ORM\Column]
    public int $delay;

    #[ORM\Column]
    public int $instance;

    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
