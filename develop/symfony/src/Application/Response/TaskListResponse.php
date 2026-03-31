<?php
declare(strict_types=1);

namespace App\Application\Response;

use App\Application\DTO\TaskItemDTO;

readonly class TaskListResponse
{
    /**
     * @param TaskItemDTO[] $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $limit,
        public int $totalPages,
    ) {}

    public static function fromDomain(array $items, int $total, int $page, int $limit): self
    {
        return new self(
            items: array_map(fn(array $item) => TaskItemDTO::fromDomain($item['task'], $item['video'], $item['preset']), $items),
            total: $total,
            page: $page,
            limit: $limit,
            totalPages: (int) ceil($total / $limit),
        );
    }
}
