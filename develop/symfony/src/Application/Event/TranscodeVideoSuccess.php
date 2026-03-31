<?php
declare(strict_types=1);

namespace App\Application\Event;

final readonly class TranscodeVideoSuccess extends ApplicationEvent
{
    public function __construct(
        public string $taskId,
        public ?string $videoId,
    ) {
    }
}
