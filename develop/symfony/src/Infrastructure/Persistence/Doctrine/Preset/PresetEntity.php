<?php

namespace App\Infrastructure\Persistence\Doctrine\Preset;

use App\Infrastructure\Persistence\Doctrine\Task\TaskEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV4 as Uuid;

#[ORM\Entity(repositoryClass: PresetRepository::class)]
#[ORM\Table(name: 'preset')]
class PresetEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    public ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    public string $title;

    #[ORM\Column]
    public int $width;

    #[ORM\Column]
    public int $height;

    #[ORM\Column(length: 50)]
    public string $codec;

    #[ORM\Column(type: 'float')]
    public float $bitrate;

    #[ORM\Column(type: 'json', nullable: true, options: ['jsonb' => true])]
    public ?array $log = null;

    /** @var Collection<int, TaskEntity> */
    #[ORM\OneToMany(targetEntity: TaskEntity::class, mappedBy: 'preset')]
    public Collection $tasks;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->tasks = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
