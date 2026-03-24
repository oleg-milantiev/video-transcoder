<?php

namespace App\Application\DTO;

use App\Domain\Video\Entity\Video;
use App\Domain\Video\Service\Storage\StorageInterface;

readonly class VideoItemDTO
{
    private function __construct(
        public string $uuid,
        public string $title,
        public string $createdAt,
        public bool $deleted,
        public ?string $poster = null,
    ) {}

    public static function fromDomain(Video $video, StorageInterface $storage): self
    {
        $hasPreview = ($video->meta()['preview'] ?? false) === true;
        $poster = $hasPreview ? $storage->publicUrl($storage->previewKey($video)) : null;

        return new self(
            uuid: $video->id()?->toRfc4122() ?? '',
            title: $video->title()->value(),
            createdAt: $video->createdAt()->format('Y-m-d H:i'),
            deleted: $video->isDeleted(),
            poster: $poster,
        );
    }
}
