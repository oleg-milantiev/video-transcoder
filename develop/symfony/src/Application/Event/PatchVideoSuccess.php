<?php
declare(strict_types=1);

namespace App\Application\Event;

final readonly class PatchVideoSuccess extends ApplicationEvent
{
    public function __construct(
        public string $videoId,
        public string $requestedByUserId,
        public string $wasTitle,
        public string $nowTitle,
    ) {
    }
}
