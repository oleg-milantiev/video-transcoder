<?php
declare(strict_types=1);

namespace App\Application\Event;

final readonly class TranscodeVideoStart extends ApplicationEvent
{
    public function __construct(
        public string $taskId,
        public string $userId,
        public string $videoId,
    ) {
    }
}
