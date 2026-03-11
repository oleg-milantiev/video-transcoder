<?php

namespace App\Application\DTO;

use App\Domain\Video\Entity\Task;

readonly class TaskItemDTO
{
    private function __construct(
        public string $videoTitle,
        public string $presetTitle,
        public string $status,
        public int $progress,
        public string $createdAt
    ) {}

    public static function fromDomain(Task $task): self
    {
        return new self(
            videoTitle: $task->video()->title()->value(),
            // TODO preset name -> title
            presetTitle: $task->preset()->name()->value(),
            status: $task->status()->name,
            progress: $task->progress()->value(),
            createdAt: $task->createdAt()->format('Y-m-d H:i')
        );
    }
}
