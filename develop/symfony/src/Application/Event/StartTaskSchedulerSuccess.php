<?php

namespace App\Application\Event;

final readonly class StartTaskSchedulerSuccess extends ApplicationEvent
{
    public function __construct(
        public int $scheduledCount,
    ) {
    }
}
