<?php

namespace App\Application\Response;

use App\Application\DTO\VideoItemDTO;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Service\Storage\StorageInterface;

readonly class VideoListResponse
{
    /**
     * @param VideoItemDTO[] $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $limit,
        public int $totalPages,
    ) {}

    public static function fromDomain(array $items, int $total, int $page, int $limit, StorageInterface $storage): self
    {
        return new self(
            items: array_map(fn(Video $video) => VideoItemDTO::fromDomain($video, $storage), $items),
            total: $total,
            page: $page,
            limit: $limit,
            totalPages: (int) ceil($total / $limit),
        );
    }
}
