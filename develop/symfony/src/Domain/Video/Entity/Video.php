<?php

namespace App\Domain\Video\Entity;

use App\Domain\User\Entity\User;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoStatus;
use App\Domain\Video\ValueObject\VideoTitle;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Uid\Uuid;

class Video
{
    private ?Uuid $id = null;
    private VideoTitle $title;
    private FileExtension $extension;
    private string $previewPath;
    private VideoStatus $status;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt = null;
    private User $user;
    private Collection $tasks;

    public function __construct(
        VideoTitle $title,
        FileExtension $extension,
        string $previewPath,
        VideoStatus $status,
        \DateTimeImmutable $createdAt,
        User $user,
        ?string $id = null,
    ) {
        $this->id = $id ? Uuid::fromString($id) : Uuid::v4();

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

    public function title(): VideoTitle
    {
        return $this->title;
    }

    public function extension(): FileExtension
    {
        return $this->extension;
    }

    public function previewPath(): string
    {
        return $this->previewPath;
    }

    public function status(): VideoStatus
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
        return $this->id->toString() . DIRECTORY_SEPARATOR . 'src.' . $this->extension->value();
    }
}
