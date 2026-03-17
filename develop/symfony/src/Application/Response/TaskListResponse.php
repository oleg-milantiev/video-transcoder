<?php

namespace App\Application\Response;

use App\Application\DTO\TaskItemDTO;

readonly class TaskListResponse
{
    /**
     * @param TaskItemDTO[] $items
     */
    public array $items;
    public int $total;
    public int $page;
    public int $limit;
    public int $totalPages;

    public static function fromDomain(array $items, int $total, int $page, int $limit): self
    {
        $instance = new self();

        $instance->items = array_map(fn(array $item) => TaskItemDTO::fromDomain($item['task'], $item['video'], $item['preset']), $items);
        $instance->total = $total;
        $instance->page = $page;
        $instance->limit = $limit;
        $instance->totalPages = (int) ceil($total / $limit);

        return $instance;
    }
}
