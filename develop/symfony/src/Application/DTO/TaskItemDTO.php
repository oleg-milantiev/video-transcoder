<?php
declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Entity\Video;

readonly class TaskItemDTO
{
    public function __construct(
        public string $id,
        public string $videoTitle,
        public string $presetId,
        public string $presetTitle,
        public int $height,
        public string $status,
        public int $progress,
        public string $createdAt,
        public bool $deleted = false,
        public ?bool $waitingTariffInstance = null,
        public ?bool $waitingTariffDelay = null,
        public ?string $willStartAt = null,
    ) {}

    public static function fromDomain(Task $task, Video $video, Preset $preset): self
    {
        if ($task->id() === null) {
            throw new \DomainException('Task id must be set for TaskItemDTO mapping.');
        }

        return new self(
            id: $task->id()->toRfc4122(),
            videoTitle: $video->title()->value(),
            presetId: $preset->id()->toRfc4122(),
            presetTitle: $preset->label(),
            height: $task->meta()['height'] ?? 'unknown',
            status: $task->status()->name,
            progress: $task->progress()->value(),
            createdAt: $task->createdAt()->format(\DateTimeInterface::ATOM),
            deleted: $task->isDeleted(),
        );
    }
}
