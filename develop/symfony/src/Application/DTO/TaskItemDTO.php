<?php

namespace App\Application\DTO;

use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Entity\Video;

readonly class TaskItemDTO
{
    private function __construct(
        public int $id,
        public string $videoTitle,
        public string $presetTitle,
        public string $status,
        public int $progress,
        public string $createdAt
    ) {}

    public static function fromDomain(Task $task, Video $video, Preset $preset): self
    {
        if ($task->id() === null) {
            throw new \DomainException('Task id must be set for TaskItemDTO mapping.');
        }

        return new self(
            id: $task->id(),
            videoTitle: $video->title()->value(),
            presetTitle: $preset->title()->value(),
            status: $task->status()->name,
            progress: $task->progress()->value(),
            createdAt: $task->createdAt()->format('Y-m-d H:i')
        );
    }
}
