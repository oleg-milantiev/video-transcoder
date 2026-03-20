<?php

namespace App\Domain\Video\ValueObject;

use App\Domain\Video\Exception\InvalidTaskDates;

final readonly class TaskDates
{
    private function __construct(
        private \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $startedAt,
        private ?\DateTimeImmutable $updatedAt,
    ) {
        if ($this->startedAt !== null && $this->startedAt < $this->createdAt) {
            throw InvalidTaskDates::startedAtBeforeCreatedAt();
        }

        if ($this->updatedAt !== null && $this->updatedAt < $this->createdAt) {
            throw InvalidTaskDates::updatedAtBeforeCreatedAt();
        }

        if ($this->startedAt !== null && $this->updatedAt !== null && $this->updatedAt < $this->startedAt) {
            throw InvalidTaskDates::updatedAtBeforeStartedAt();
        }
    }

    public static function create(?\DateTimeImmutable $createdAt = null): self
    {
        return new self($createdAt ?? new \DateTimeImmutable(), null, null);
    }

    public static function fromPersistence(
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $startedAt,
        ?\DateTimeImmutable $updatedAt,
    ): self {
        return new self($createdAt, $startedAt, $updatedAt);
    }

    public function markStarted(?\DateTimeImmutable $startedAt = null): self
    {
        if ($this->startedAt !== null) {
            throw InvalidTaskDates::alreadyStarted();
        }

        $startedAt ??= new \DateTimeImmutable();

        return new self($this->createdAt, $startedAt, $startedAt);
    }

    public function restart(): self
    {
        return new self($this->createdAt, null, new \DateTimeImmutable());
    }

    public function touch(?\DateTimeImmutable $updatedAt = null): self
    {
        return new self($this->createdAt, $this->startedAt, $updatedAt ?? new \DateTimeImmutable());
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function startedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}

