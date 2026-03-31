<?php
declare(strict_types=1);

namespace App\Application\Event;

final readonly class StartTranscodeStart extends ApplicationEvent
{
    public function __construct(
        public string $videoId,
        public string $presetId,
        public string $userId,
    ) {
    }
}
