<?php

namespace App\Application\Event;

final readonly class TranscodeVideoStart extends ApplicationEvent
{
    public function __construct(
        public int $taskId,
        public int $userId,
        public string $videoId,
    ) {
    }
}
