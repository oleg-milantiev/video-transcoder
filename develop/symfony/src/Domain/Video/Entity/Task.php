<?php

namespace App\Domain\Video\Entity;

use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskDates;
use App\Domain\Video\ValueObject\TaskStatus;
use Symfony\Component\Uid\UuidV4 as Uuid;

class Task
{
    private ?Uuid $id;
    private TaskStatus $status;
    private Progress $progress;
    private TaskDates $dates;
    private array $meta;
    private Uuid $videoId;
    private Uuid $presetId;
    private Uuid $userId;

    private function __construct(
        Uuid $videoId,
        Uuid $presetId,
        Uuid $userId,
        TaskStatus $status,
        Progress $progress,
        TaskDates $dates,
        ?Uuid $id,
        array $meta,
    ) {
        $this->id = $id;
        $this->videoId = $videoId;
        $this->presetId = $presetId;
        $this->status = $status;
        $this->progress = $progress;
        $this->dates = $dates;
        $this->meta = $meta;
        $this->userId = $userId;
    }

    public static function create(Uuid $videoId, Uuid $presetId, Uuid $userId): self
    {
        return new self(
            videoId: $videoId,
            presetId: $presetId,
            userId: $userId,
            status: TaskStatus::pending(),
            progress: new Progress(0),
            dates: TaskDates::create(),
            id: null,
            meta: [],
        );
    }

    public static function reconstitute(
        Uuid $videoId,
        Uuid $presetId,
        Uuid $userId,
        TaskStatus $status,
        Progress $progress,
        TaskDates $dates,
        Uuid $id,
        array $meta = [],
    ): self {
        return new self($videoId, $presetId, $userId, $status, $progress, $dates, $id, $meta);
    }

    public function canStart(?float $videoDuration): bool
    {
        if (!$this->status->canBeStarted()) {
            return false;
        }

        return $videoDuration !== null && $videoDuration > 0.0;
    }

    public function start(?float $videoDuration): void
    {
        if (!$this->canStart($videoDuration)) {
            throw new \DomainException('Task cannot be started.');
        }

        $this->status = TaskStatus::processing();
        $this->dates = $this->dates->markStarted();
    }

    public function restart(): void
    {
        if (!$this->status->canBeRestarted()) {
            throw new \DomainException('Task cannot be restarted.');
        }

        $this->status = TaskStatus::pending();
        $this->progress = new Progress(0);
        $this->dates = $this->dates->restart();
    }

    public function updateProgress(Progress $progress): void
    {
        if ($this->status !== TaskStatus::PROCESSING) {
            throw new \DomainException('Cannot update progress for task that is not processing.');
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

    public function canBeCancelled(): bool
    {
        return $this->status === TaskStatus::PENDING || $this->status === TaskStatus::PROCESSING;
    }

    public function cancel(): void
    {
        if (!$this->canBeCancelled()) {
            throw new \DomainException('Task cannot be cancelled.');
        }

        $this->status = TaskStatus::cancelled();
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

    public function presetId(): Uuid
    {
        return $this->presetId;
    }

    public function status(): TaskStatus
    {
        return $this->status;
    }

    public function id(): ?Uuid
    {
        return $this->id;
    }

    public function assignId(Uuid $id): void
    {
        if ($this->id !== null && !$this->id->equals($id)) {
            throw new \DomainException('Task id is already assigned and cannot be changed.');
        }

        $this->id = $id;
    }

    public function meta(): array
    {
        return $this->meta;
    }

    public function userId(): Uuid
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
