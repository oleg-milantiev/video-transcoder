<?php
declare(strict_types=1);

namespace App\Application\DTO;

readonly class PresetWithTaskDTO
{
    public function __construct(
        public string $id,
        public string $title,
        public int $expectedFileSize,
        public ?TaskInfoDTO $task = null,
    ) {}
}

