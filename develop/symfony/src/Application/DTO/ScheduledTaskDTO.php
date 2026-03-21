<?php

namespace App\Application\DTO;

use Symfony\Component\Uid\UuidV4;

final readonly class ScheduledTaskDTO
{
    public function __construct(
        public int $taskId,
        public int $userId,
        public UuidV4 $videoId,
    ) {
    }
}
