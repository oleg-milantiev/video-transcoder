<?php

namespace App\Domain\Video\Entity;

use App\Domain\User\Entity\User;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Uid\Uuid;

class Video
{
    private ?Uuid $id = null;
    private string $title;
    private string $extension;
    private string $previewPath;
    private string $status;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt = null;
    private User $user;
    private Collection $tasks;

    public function __construct(
        string $title,
        string $extension,
        string $previewPath,
        string $status,
        \DateTimeImmutable $createdAt,
        User $user,
        ?int $id = null,
    ) {
        $this->id = $id ? Uuid::fromString($id) : Uuid::v4();

        // TODO через бизнес-логику
        $this->title = $title;
        $this->extension = $extension;
        $this->previewPath = $previewPath;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->user = $user;
    }

    public function id(): ?Uuid
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function extension(): string
    {
        return $this->extension;
    }

    public function previewPath(): string
    {
        return $this->previewPath;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function user(): User
    {
        return $this->user;
    }

    public function tasks(): Collection
    {
        return $this->tasks;
    }

    public function getSrcFilename(): string
    {
        return $this->id->toString() . DIRECTORY_SEPARATOR . 'src.' . $this->extension;
    }
}
