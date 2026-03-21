<?php

namespace App\Application\Event;

final readonly class CreateVideoPreviewFail extends ApplicationEvent
{
    public function __construct(
        public string $error,
        public ?string $videoId,
    ) {
    }
}
