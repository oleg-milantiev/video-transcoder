<?php

namespace App\Entity;

use App\Repository\PresetRepository;
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
    public ?string $name = null;

    #[ORM\Column(length: 20)]
    public ?string $resolution = null;

    #[ORM\Column(length: 50)]
    public ?string $codec = null;

    #[ORM\Column]
    public ?int $bitrate = null;

    /**
     * @var Collection<int, Task>
     */
    #[ORM\OneToMany(targetEntity: Task::class, mappedBy: 'preset')]
    public Collection $tasks;

    public function __construct()
    {
        $this->tasks = new ArrayCollection();
    }

    public function addTask(Task $task): static
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks->add($task);
            $task->preset = $this;
        }

        return $this;
    }

    public function removeTask(Task $task): static
    {
        if ($this->tasks->removeElement($task)) {
            // set the owning side to null (unless already changed)
            if ($task->preset === $this) {
                $task->preset = null;
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
