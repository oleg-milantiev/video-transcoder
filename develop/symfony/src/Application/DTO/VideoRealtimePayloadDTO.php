<?php
declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Video\Entity\Video;

final class VideoRealtimePayloadDTO
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public string $videoId,
        public string $title,
        public ?string $poster,
        public array $meta,
        public string $createdAt,
        public ?string $updatedAt,
        public bool $deleted,
        public bool $canBeDeleted,
    ) {
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function fromVideo(Video $video, ?string $poster, array $tasks): self
    {
        $canBeDeleted = true;
        foreach ($tasks as $task) {
            if (!$task->status()->canBeDeleted()) {
                $canBeDeleted = false;
                break;
            }
        }

        return new self(
            videoId: $video->id()->toRfc4122(),
            title: $video->title()->value(),
            poster: $poster,
            meta: $video->meta(),
            createdAt: $video->createdAt()->format(DATE_ATOM),
            updatedAt: $video->updatedAt()?->format(DATE_ATOM),
            deleted: $video->isDeleted(),
            canBeDeleted: $canBeDeleted,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'videoId' => $this->videoId,
            'title' => $this->title,
            'poster' => $this->poster,
            'meta' => $this->meta,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'deleted' => $this->deleted,
            'canBeDeleted' => $this->canBeDeleted,
        ];
    }
}
