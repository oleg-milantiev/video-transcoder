<?php

namespace App\Application\Event;

final readonly class TranscodeVideoFail extends ApplicationEvent
{
    public function __construct(
        public string $error,
        public ?int $taskId = null,
        public ?string $videoId = null,
    ) {
    }
}
