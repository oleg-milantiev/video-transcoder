<?php

namespace App\Application\Event;

final readonly class DeleteVideoFail extends ApplicationEvent
{
    public function __construct(
        public string $error,
        public ?string $videoId = null,
        public ?string $requestedByUserId = null,
    ) {
    }
}
