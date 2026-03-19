<?php

namespace App\Application\DTO;

use App\Domain\Video\Entity\Video;

readonly class VideoDetailsDTO
{
    public function __construct(
        public string $id,
        public string $title,
        public string $extension,
        public string $status,
        public string $createdAt,
        public ?string $updatedAt,
        public int $userId,
        public array $meta,
        public ?string $poster,
        /** @var PresetWithTaskDTO[] */
        public array $presetsWithTasks = [],
    ) {}

    public static function fromDomain(Video $video, array $presetsWithTasks): self
    {
        return new self(
            id: $video->id()->toRfc4122(),
            title: $video->title()->value(),
            extension: $video->extension()->value(),
            status: $video->status()->name,
            createdAt: $video->createdAt()->format('Y-m-d H:i'),
            updatedAt: $video->updatedAt()?->format('Y-m-d H:i'),
            userId: $video->userId(),
            meta: self::sanitizeMeta($video->meta()),
            poster: $video->getPoster(),
            presetsWithTasks: $presetsWithTasks,
        );
    }

    protected static function sanitizeMeta(array $meta): array
    {
        unset($meta['preview']);

        return $meta;
    }
}
