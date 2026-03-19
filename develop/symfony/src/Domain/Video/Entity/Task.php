<?php

namespace App\Domain\Video\Entity;

use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskDates;
use App\Domain\Video\ValueObject\TaskStatus;
use Symfony\Component\Uid\UuidV4 as Uuid;

class Task
{
    private ?int $id;
    private TaskStatus $status;
    private Progress $progress;
    private TaskDates $dates;
    private array $meta;
    private Uuid $videoId;
    private int $presetId;
    private int $userId;

    // Constructor for mapping from Doctrine only. Use static::create() for domain
    // TODO remove it
    public function __construct(
        Uuid $videoId,
        int $presetId,
        int $userId,
        ?TaskStatus $status = null,
        ?Progress $progress = null,
        ?TaskDates $dates = null,
        ?int $id = null,
        array $meta = [],
    ) {
        $this->id = $id;
        $this->videoId = $videoId;
        $this->presetId = $presetId;
        $this->status = $status ?? TaskStatus::pending();
        $this->progress = $progress ?? new Progress(0);
        $this->dates = $dates ?? TaskDates::create();
        $this->meta = $meta;
        $this->userId = $userId;
    }

    public static function create(Uuid $videoId, int $presetId, int $userId): self
    {
        return new self($videoId, $presetId, $userId);
    }

    public function start(): void
    {
        if (!$this->status->canBeStarted()) {
            throw new \DomainException('Task cannot be started.');
        }

        $this->status = TaskStatus::processing();
        $this->dates = $this->dates->markStarted();
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
        return $this->dates->createdAt();
    }

    public function startedAt(): ?\DateTimeImmutable
    {
        return $this->dates->startedAt();
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->dates->updatedAt();
    }

    public function videoId(): Uuid
    {
        return $this->videoId;
    }

    public function presetId(): int
    {
        return $this->presetId;
    }

    public function status(): TaskStatus
    {
        return $this->status;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function meta(): array
    {
        return $this->meta;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function updateMeta(array $meta): void
    {
        $this->meta = array_merge($this->meta, $meta);
        $this->touch();
    }

    private function touch(): void
    {
        $this->dates = $this->dates->touch();
    }
}
