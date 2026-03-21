<?php

namespace App\Application\Event;

final readonly class StartTranscodeFail extends ApplicationEvent
{
    public function __construct(
        public string $error,
        public string $videoId,
        public int $presetId,
        public int $userId,
    ) {
    }
}
