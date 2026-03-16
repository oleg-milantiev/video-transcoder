<?php

namespace App\Application\DTO;

readonly class TaskInfoDTO
{
    public function __construct(
        public string $status,
        public int $progress,
        public string $createdAt,
    ) {}
}
