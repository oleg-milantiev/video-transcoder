<?php
declare(strict_types=1);

namespace App\Application\Event;

final readonly class CreateVideoSuccess extends ApplicationEvent
{
    public function __construct(
        public ?string $videoId,
        public string $userId,
    ) {
    }
}
