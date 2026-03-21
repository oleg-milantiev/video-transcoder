<?php

namespace App\Application\Event;

final readonly class StartTranscodeSuccess extends ApplicationEvent
{
    public function __construct(
        public int $taskId,
        public string $videoId,
        public int $presetId,
        public int $userId,
    ) {
    }
}
