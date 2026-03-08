<?php

namespace App\Infrastructure\Persistence\Doctrine\User;

use App\Infrastructure\Persistence\Doctrine\User\UserRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TarifRepository::class)]
#[ORM\Table(name: 'tarif')]
class TarifEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 255)]
    public ?string $title = null;

    #[ORM\Column]
    public ?int $timeDelay = null;

    public function __toString(): string
    {
        return (string) $this->title;
    }
}
