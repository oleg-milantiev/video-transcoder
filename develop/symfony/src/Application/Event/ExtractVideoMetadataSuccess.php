<?php

namespace App\Application\Event;

final readonly class ExtractVideoMetadataSuccess extends ApplicationEvent
{
    public function __construct(
        public ?string $videoId,
    ) {
    }
}
