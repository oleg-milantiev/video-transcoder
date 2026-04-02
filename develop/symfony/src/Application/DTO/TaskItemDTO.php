<?php
declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Entity\Video;

readonly class TaskItemDTO
{
    private function __construct(
        public string $id,
        public string $videoTitle,
        public string $presetTitle,
        public string $status,
        public int $progress,
        public string $createdAt,
        public string $downloadFilename,
        public bool $deleted = false,
    ) {}

    public static function fromDomain(Task $task, Video $video, Preset $preset): self
    {
        if ($task->id() === null) {
            throw new \DomainException('Task id must be set for TaskItemDTO mapping.');
        }

        return new self(
            id: $task->id()->toRfc4122(),
            videoTitle: $video->title()->value(),
            presetTitle: $preset->title()->value(),
            status: $task->status()->name,
            progress: $task->progress()->value(),
            createdAt: $task->createdAt()->format(\DateTimeInterface::ATOM),
            downloadFilename: $video->title()->value() . ' - ' . $preset->title()->value(),
            deleted: $task->isDeleted(),
        );
    }
}
