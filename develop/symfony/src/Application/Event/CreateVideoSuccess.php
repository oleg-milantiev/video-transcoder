<?php

namespace App\Application\Event;

final readonly class CreateVideoSuccess extends ApplicationEvent
{
    public function __construct(
        public ?string $videoId,
        public int $userId,
    ) {
    }
}
