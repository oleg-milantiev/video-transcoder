<?php

namespace App\Domain\Video\DTO;

readonly class PaginatedResult
{
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
