<?php
declare(strict_types=1);

namespace App\Domain\Video\DTO;

readonly class PaginatedResult
{
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
