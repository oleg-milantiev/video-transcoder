<?php

namespace App\Domain\Video\Entity;

use App\Infrastructure\Persistence\Doctrine\Repository\PresetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PresetRepository::class)]
class Preset
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 255)]
    public string $name;

    // В идеале resolution, codec и bitrate стоит вынести в Value Objects
    #[ORM\Column(length: 20)]
    public string $resolution;

    #[ORM\Column(length: 50)]
    public string $codec;

    #[ORM\Column]
    public int $bitrate;

    /** @var Collection<int, Task> */
    #[ORM\OneToMany(targetEntity: Task::class, mappedBy: 'preset')]
    public Collection $tasks;

    public function __construct()
    {
        $this->tasks = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
