<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Entity\Video;

final class TaskRealtimePayloadDTO
{
    public function __construct(
        public string $taskId,
        public string $videoId,
        public string $presetId,
        public string $status,
        public int $progress,
        public string $createdAt,
        public ?string $updatedAt,
        public bool $deleted,

        public ?string $videoTitle = null,
        public ?string $presetTitle = null,
    ) {
    }

    public static function fromTask(Task $task): self
    {
        return new self(
            taskId: $task->id()->toRfc4122(),
            videoId: $task->videoId()->toRfc4122(),
            presetId: $task->presetId()->toRfc4122(),
            status: $task->status()->name,
            progress: $task->progress()->value(),
            createdAt: $task->createdAt()->format('Y-m-d H:i'),
            updatedAt: $task->updatedAt()?->format('Y-m-d H:i'),
            deleted: $task->isDeleted(),
        );
    }

    public function addVideoPresetFields(Video $video, Preset $preset): void
    {
        $this->videoTitle = $video->title()->value();
        $this->presetTitle = $preset->title()->value();
    }

    public function toArray(): array
    {
        return [
            'taskId' => $this->taskId,
            'videoId' => $this->videoId,
            'presetId' => $this->presetId,
            'status' => $this->status,
            'progress' => $this->progress,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'deleted' => $this->deleted,

            'videoTitle' => $this->videoTitle,
            'presetTitle' => $this->presetTitle,
        ];
    }
}

