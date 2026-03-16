<?php

namespace App\Application\DTO;

use App\Domain\Video\Entity\Video;

readonly class VideoItemDTO
{
    private function __construct(
        public string $uuid,
        public string $title,
        public string $status,
        public string $createdAt,
        public ?string $poster = null,
    ) {}

    public static function fromDomain(Video $video): self
    {
        $poster = $video->getPoster();
        return new self(
            uuid: $video->id()?->toRfc4122() ?? '',
            title: $video->title()->value(),
            status: $video->status()->name,
            createdAt: $video->createdAt()->format('Y-m-d H:i'),
            poster: $poster !== null ? $poster : null,
        );
    }
}
