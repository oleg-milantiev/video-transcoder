<?php

namespace App\Application\DTO;

use App\Domain\Video\Entity\Video;

readonly class VideoItemDTO
{
    private function __construct(
        public string $id,
        public string $title,
        public string $status,
        public string $createdAt
    ) {}

    public static function fromDomain(Video $video): self
    {
        return new self(
            id: $video->id()->toString(),
            title: $video->title()->value(),
            status: $video->status()->name,
            createdAt: $video->createdAt()->format('Y-m-d H:i')
        );
    }
}
