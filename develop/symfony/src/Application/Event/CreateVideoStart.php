<?php
declare(strict_types=1);

namespace App\Application\Event;

final readonly class CreateVideoStart extends ApplicationEvent
{
    public function __construct(
        public string $userId,
        public string $filename,
    ) {
    }
}
