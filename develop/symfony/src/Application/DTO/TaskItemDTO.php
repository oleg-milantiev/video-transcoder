<?php

namespace App\Application\DTO;

use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Entity\Video;

readonly class TaskItemDTO
{
    private function __construct(
        public string $videoTitle,
        public string $presetTitle,
        public string $status,
        public int $progress,
        public string $createdAt
    ) {}

    public static function fromDomain(Task $task, Video $video, Preset $preset): self
    {
        return new self(
            videoTitle: $video->title()->value(),
            // TODO preset name -> title
            presetTitle: $preset->name()->value(),
            status: $task->status()->name,
            progress: $task->progress()->value(),
            createdAt: $task->createdAt()->format('Y-m-d H:i')
        );
    }
}
