<?php

namespace App\Domain\Video\Entity;

class Task
{
    private ?int $id = null;
    private string $status;
    private int $progress;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt = null;
    private Video $video;
    private Preset $preset;

    public function __construct(
        string $status,
        int $progress,
        \DateTimeImmutable $createdAt,
        Video $video,
        Preset $preset,
        ?\DateTimeImmutable $updatedAt = null,
        ?int $id = null,
    ) {
        $this->id = $id;

        // TODO через бизнес логику
        $this->status = $status;
        $this->progress = $progress;
        $this->video = $video;
        $this->preset = $preset;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $updatedAt;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function progress(): int
    {
        return $this->progress;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function video(): Video
    {
        return $this->video;
    }

    public function preset(): Preset
    {
        return $this->preset;
    }
}
