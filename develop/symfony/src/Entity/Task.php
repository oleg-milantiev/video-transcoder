<?php

namespace App\Entity;

use App\Repository\TaskRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'task')]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 50)]
    public ?string $status = 'pending';

    #[ORM\Column]
    public ?int $progress = 0;

    #[ORM\Column]
    public ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'transcodingTasks')]
    #[ORM\JoinColumn(nullable: false)]
    public ?Video $video = null;

    #[ORM\ManyToOne(inversedBy: 'transcodingTasks')]
    #[ORM\JoinColumn(nullable: false)]
    public ?Preset $preset = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return sprintf('Task #%d (%s)', $this->id, $this->status);
    }
}
