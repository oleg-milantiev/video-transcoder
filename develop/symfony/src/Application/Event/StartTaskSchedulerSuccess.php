<?php
declare(strict_types=1);

namespace App\Application\Event;

final readonly class StartTaskSchedulerSuccess extends ApplicationEvent
{
    public function __construct(
        public int $scheduledCount,
    ) {
    }
}
