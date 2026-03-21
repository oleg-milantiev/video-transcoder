<?php

namespace App\Application\Event;

final readonly class CreateVideoFail extends ApplicationEvent
{
    public function __construct(
        public string $error,
        public string $userId,
        public string $filename,
    ) {
    }
}
