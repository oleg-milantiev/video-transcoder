<?php

namespace App\Application\Event;

final readonly class DeleteVideoSuccess extends ApplicationEvent
{
    public function __construct(
        public string $videoId,
        public string $requestedByUserId,
        public int $deletedTaskCount,
    ) {
    }
}
