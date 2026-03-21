<?php

namespace App\Application\Event;

final readonly class ExtractVideoMetadataStart extends ApplicationEvent
{
    public function __construct(
        public ?string $videoId,
    ) {
    }
}
