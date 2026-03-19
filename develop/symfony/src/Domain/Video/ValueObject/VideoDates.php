<?php

namespace App\Domain\Video\ValueObject;

final readonly class VideoDates
{
    private function __construct(
        private \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
    ) {
        if ($this->updatedAt !== null && $this->updatedAt < $this->createdAt) {
            throw new \DomainException('updatedAt cannot be earlier than createdAt.');
        }
    }

    public static function create(?\DateTimeImmutable $createdAt = null): self
    {
        return new self($createdAt ?? new \DateTimeImmutable(), null);
    }

    public static function fromPersistence(\DateTimeImmutable $createdAt, ?\DateTimeImmutable $updatedAt): self
    {
        return new self($createdAt, $updatedAt);
    }

    public function touch(?\DateTimeImmutable $updatedAt = null): self
    {
        return new self($this->createdAt, $updatedAt ?? new \DateTimeImmutable());
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}

