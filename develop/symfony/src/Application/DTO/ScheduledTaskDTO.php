<?php

namespace App\Application\DTO;

use Symfony\Component\Uid\UuidV4;

final readonly class ScheduledTaskDTO
{
    public function __construct(
        public UuidV4 $taskId,
        public UuidV4 $userId,
        public UuidV4 $videoId,
    ) {
    }
}
