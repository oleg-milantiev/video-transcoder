<?php

namespace App\Application\Event;

final readonly class DeleteVideoStart extends ApplicationEvent
{
    public function __construct(
        public string $videoId,
        public string $requestedByUserId,
    ) {
    }
}
