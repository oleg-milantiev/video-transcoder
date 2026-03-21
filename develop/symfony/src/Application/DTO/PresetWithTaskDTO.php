<?php

namespace App\Application\DTO;

readonly class PresetWithTaskDTO
{
    public function __construct(
        public string $id,
        public string $title,
        public ?TaskInfoDTO $task = null,
    ) {}
}

