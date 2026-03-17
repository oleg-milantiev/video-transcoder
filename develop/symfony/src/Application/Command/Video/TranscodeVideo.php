<?php

namespace App\Application\Command\Video;

use App\Domain\Video\DTO\ScheduledTaskDTO;

final readonly class TranscodeVideo
{
    public function __construct(public ScheduledTaskDTO $scheduledTask)
    {
    }
}

