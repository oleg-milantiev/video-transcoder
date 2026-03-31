<?php
declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Shared\ValueObject\Uuid;

final readonly class ScheduledTaskDTO
{
    public function __construct(
        public Uuid $taskId,
        public Uuid $userId,
        public Uuid $videoId,
    ) {
    }
}
