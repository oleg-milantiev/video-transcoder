<?php

namespace App\Application\Event;

final readonly class ExtractVideoMetadataFail extends ApplicationEvent
{
    public function __construct(
        public string $error,
        public ?string $videoId,
    ) {
    }
}
