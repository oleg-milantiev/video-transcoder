<?php
declare(strict_types=1);

namespace App\Application\Command\Task;

use App\Application\DTO\ScheduledTaskDTO;

final readonly class TranscodeVideo
{
    public function __construct(public ScheduledTaskDTO $scheduledTask)
    {
    }
}

