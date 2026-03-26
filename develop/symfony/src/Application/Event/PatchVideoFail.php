<?php

namespace App\Application\Event;

final readonly class PatchVideoFail extends ApplicationEvent
{
    public function __construct(
        public string $error,
        public ?string $videoId = null,
    ) {
    }
}
