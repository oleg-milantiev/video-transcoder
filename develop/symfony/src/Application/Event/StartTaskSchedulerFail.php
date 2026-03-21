<?php

namespace App\Application\Event;

final readonly class StartTaskSchedulerFail extends ApplicationEvent
{
    public function __construct(
        public string $error,
    ) {
    }
}
