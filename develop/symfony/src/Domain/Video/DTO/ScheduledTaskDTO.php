<?php

namespace App\Domain\Video\DTO;

final readonly class ScheduledTaskDTO
{
    public function __construct(
        public int $taskId,
        public int $userId,
        public int $videoId,
    ) {
    }
}

