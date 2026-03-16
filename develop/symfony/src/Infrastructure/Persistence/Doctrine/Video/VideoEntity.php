<?php

namespace App\Infrastructure\Persistence\Doctrine\Video;

use App\Domain\Video\ValueObject\VideoStatus;
use App\Infrastructure\Persistence\Doctrine\Task\TaskEntity;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV4 as Uuid;

#[ORM\Entity(repositoryClass: VideoRepository::class)]
#[ORM\Table(name: 'video')]
class VideoEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    public ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    public ?string $title = null;

    #[ORM\Column(length: 10)]
    public ?string $extension = null;

    #[ORM\Column]
    public int $status = VideoStatus::UPLOADING->value;

    #[ORM\Column]
    public ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'videos')]
    #[ORM\JoinColumn(nullable: false)]
    public ?UserEntity $user = null;

    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    public array $meta = [];

    #[ORM\Column(type: 'json', nullable: true, options: ['jsonb' => true])]
    public ?array $log = null;

    /**
     * @var Collection<int, TaskEntity>
     */
    #[ORM\OneToMany(targetEntity: TaskEntity::class, mappedBy: 'video', orphanRemoval: true)]
    public Collection $tasks;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->tasks = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
