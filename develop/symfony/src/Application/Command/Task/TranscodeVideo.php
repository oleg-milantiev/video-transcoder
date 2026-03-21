<?php

namespace App\Application\Command\Task;

use App\Application\DTO\ScheduledTaskDTO;

final readonly class TranscodeVideo
{
    public function __construct(public ScheduledTaskDTO $scheduledTask)
    {
    }
}

