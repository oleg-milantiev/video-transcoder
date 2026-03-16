<?php

namespace App\Application\DTO;

use App\Domain\Video\Entity\Video;

readonly class VideoItemDTO
{
    private function __construct(
        public string $title,
        public ?array $meta,
        public string $status,
        public string $createdAt,
        public ?string $poster = null,
    ) {}

    public static function fromDomain(Video $video): self
    {
        $poster = $video->getPoster();
        return new self(
            title: $video->title()->value(),
            meta: $video->meta(),
            status: $video->status()->name,
            createdAt: $video->createdAt()->format('Y-m-d H:i'),
            poster: $poster !== null ? $poster : null,
        );
    }
}
