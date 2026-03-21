<?php

namespace App\Application\Event;

final readonly class TranscodeVideoSuccess extends ApplicationEvent
{
    public function __construct(
        public string $taskId,
        public ?string $videoId,
    ) {
    }
}
