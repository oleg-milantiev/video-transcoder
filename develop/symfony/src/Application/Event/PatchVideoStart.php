<?php

namespace App\Application\Event;

final readonly class PatchVideoStart extends ApplicationEvent
{
    public function __construct(
        public string $videoId,
        public string $requestedByUserId,
        public string $title,
    ) {
    }
}
