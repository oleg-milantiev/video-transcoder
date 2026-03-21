<?php

namespace App\Application\Event;

final readonly class TranscodeVideoStart extends ApplicationEvent
{
    public function __construct(
        public string $taskId,
        public string $userId,
        public string $videoId,
    ) {
    }
}
