<?php
declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Entity\Video;
use DateTimeInterface;
use DomainException;

readonly class TaskItemDTO
{
    public function __construct(
        public string $id,
        public string $videoId,
        public string $videoTitle,
        public string $presetId,
        public string $presetVideoCodec,
        public string $presetAudioCodec,
        public string $presetFormat,
        public string $presetTitle,
        public int $height,
        public string $status,
        public int $progress,
        public string $createdAt,
        public ?string $updatedAt = null,
        public bool $deleted = false,
        public ?bool $waitingTariffInstance = null,
        public ?bool $waitingTariffDelay = null,
        public ?string $willStartAt = null,
        public ?int $size = null,
    ) {
    }

    public static function fromDomain(Task $task, Video $video, Preset $preset): self
    {
        if ($task->id() === null) {
            throw new DomainException('Task id must be set for TaskItemDTO mapping.');
        }

        return new self(
            id: $task->id()->toRfc4122(),
            videoId: $video->id()->toRfc4122(),
            videoTitle: $video->title()->value(),
            presetId: $preset->id()->toRfc4122(),
            presetVideoCodec: $preset->videoCodec()->value(),
            presetAudioCodec: $preset->audioCodec()->value(),
            presetFormat: $preset->format()->value(),
            presetTitle: $preset->title()->value(),
            height: $task->heightNullable() ?? 0,
            status: $task->status()->name,
            progress: $task->progress()->value(),
            createdAt: $task->createdAt()->format(DateTimeInterface::ATOM),
            updatedAt: $task->updatedAt()?->format(DateTimeInterface::ATOM),
            deleted: $task->isDeleted(),
            size: isset($task->meta()['size']) ? (int)$task->meta()['size'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'taskId' => $this->id,
            'videoId' => $this->videoId,
            'videoTitle' => $this->videoTitle,
            'presetId' => $this->presetId,
            'presetVideoCodec' => $this->presetVideoCodec,
            'presetAudioCodec' => $this->presetAudioCodec,
            'presetFormat' => $this->presetFormat,
            'presetTitle' => $this->presetTitle,
            'height' => $this->height,
            'status' => $this->status,
            'progress' => $this->progress,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'deleted' => $this->deleted,
            'waitingTariffInstance' => $this->waitingTariffInstance,
            'waitingTariffDelay' => $this->waitingTariffDelay,
            'willStartAt' => $this->willStartAt,
            'size' => $this->size,
        ];
    }
}
