<?php

namespace App\Application\Event;

final readonly class CreateVideoStart extends ApplicationEvent
{
    public function __construct(
        public int $userId,
        public string $filename,
    ) {
    }
}
