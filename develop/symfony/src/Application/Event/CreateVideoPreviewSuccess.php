<?php

namespace App\Application\Event;

final readonly class CreateVideoPreviewSuccess extends ApplicationEvent
{
    public function __construct(
        public ?string $videoId,
    ) {
    }
}
