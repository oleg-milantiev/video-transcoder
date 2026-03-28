<?php

namespace App\Infrastructure\Persistence\Doctrine\Task;

use App\Domain\Video\ValueObject\TaskStatus;
use App\Infrastructure\Persistence\Doctrine\Preset\PresetEntity;
use App\Infrastructure\Persistence\Doctrine\Video\VideoEntity;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'task')]
#[ORM\Index(name: 'idx_task_video_id', columns: ['video_id'])]
class TaskEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    public ?SymfonyUuid $id = null;

    #[ORM\Column(length: 50)]
    public int $status = TaskStatus::PENDING->value;

    #[ORM\Column]
    public ?int $progress = 0;

    #[ORM\Column]
    public ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: 'json')]
    public array $meta = [];

    #[ORM\Column(options: ['default' => false])]
    public bool $deleted = false;

    #[ORM\ManyToOne(inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: false)]
    public ?VideoEntity $video = null;

    #[ORM\ManyToOne(inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: false)]
    public ?PresetEntity $preset = null;

    #[ORM\ManyToOne(targetEntity: UserEntity::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true)]
    public ?UserEntity $user = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return sprintf('Task #%s (%s)', $this->id?->toRfc4122() ?? 'new', TaskStatus::from($this->status)->name);
    }
}
