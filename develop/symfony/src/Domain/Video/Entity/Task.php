<?php

namespace App\Domain\Video\Entity;

use App\Domain\User\Entity\User;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskStatus;

// TODO move to videoId, presetId, userId
class Task
{
    private ?int $id = null;
    private TaskStatus $status;
    private Progress $progress;
    // TODO DDD
    private \DateTimeImmutable $createdAt;
    // TODO DDD
    private ?\DateTimeImmutable $updatedAt = null;
    private array $meta;
    private Video $video;
    private Preset $preset;
    private ?User $user = null;
    // TODO DDD PresetId, VideoId

    // Constructor for mapping from Doctrine only. Use static::create() for domain
    public function __construct(
        Video $video,
        Preset $preset,
        ?TaskStatus $status = null,
        ?Progress $progress = null,
        // TODO DDD
        ?\DateTimeImmutable $createdAt = null,
        // TODO DDD
        ?\DateTimeImmutable $updatedAt = null,
        ?int $id = null,
        array $meta = [],
        ?User $user = null,
    ) {
        $this->id = $id;
        $this->video = $video;
        $this->preset = $preset;
        $this->status = $status ?? TaskStatus::pending();
        $this->progress = $progress ?? new Progress(0);
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt;
        $this->meta = $meta;
        $this->user = $user;
    }

    public static function create(Video $video, Preset $preset, ?User $user = null): self
    {
        // TODO optimize
        return new self($video, $preset, null, null, null, null, null, [], $user);
    }

    public function start(): void
    {
        if (!$this->status->canBeStarted()) {
            throw new \DomainException('Task cannot be started.');
        }

        $this->status = TaskStatus::processing();
        $this->touch();
    }

    public function updateProgress(Progress $progress): void
    {
        if ($this->status->isFinished()) {
            throw new \DomainException('Cannot update progress of finished task.');
        }

        $this->progress = $progress;

        if ($progress->isComplete()) {
            $this->status = TaskStatus::completed();
        }

        $this->touch();
    }

    public function fail(): void
    {
        if ($this->status->isFinished()) {
            throw new \DomainException('Finished task cannot fail.');
        }

        $this->status = TaskStatus::failed();
        $this->touch();
    }

    public function progress(): Progress
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

    public function status(): TaskStatus
    {
        return $this->status;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function meta(): array
    {
        return $this->meta;
    }

    public function user(): ?User
    {
        return $this->user;
    }

    public function updateMeta(array $meta): void
    {
        $this->meta = array_merge($this->meta, $meta);
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
