<?php

namespace App\Application\Event;

final readonly class TranscodeVideoSuccess extends ApplicationEvent
{
    public function __construct(
        public int $taskId,
        public ?string $videoId,
    ) {
    }
}
