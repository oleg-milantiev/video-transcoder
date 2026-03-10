<?php

namespace App\Application\DTO;

use App\Domain\Video\Entity\Video;

readonly class VideoListResponse
{
    /**
     * @param VideoItemDTO[] $items
     */
    private function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $limit,
        public int $totalPages
    ) {}

    public static function fromDomain(array $videos, int $total, int $page, int $limit): self
    {
        return new self(
            items: array_map(fn(Video $video) => VideoItemDTO::fromDomain($video), $videos),
            total: $total,
            page: $page,
            limit: $limit,
            totalPages: (int) ceil($total / $limit)
        );
    }
}
