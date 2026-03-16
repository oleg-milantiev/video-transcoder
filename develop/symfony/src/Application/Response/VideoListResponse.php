<?php

namespace App\Application\Response;

use App\Application\DTO\VideoItemDTO;
use App\Domain\Video\Entity\Video;

readonly class VideoListResponse
{
    /**
     * @param VideoItemDTO[] $items
     */
    public array $items;
    public int $total;
    public int $page;
    public int $limit;
    public int $totalPages;

    public static function fromDomain(array $items, int $total, int $page, int $limit): self
    {
        $instance = new self();

        $instance->items = array_map(fn(Video $video) => VideoItemDTO::fromDomain($video), $items);
        $instance->total = $total;
        $instance->page = $page;
        $instance->limit = $limit;
        $instance->totalPages = (int) ceil($total / $limit);

        return $instance;
    }
}
