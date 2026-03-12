<?php

namespace App\Domain\Video\Event;

use App\Application\Command\Video\CreateVideo;

final readonly class VideoCreateFailed
{
    public function __construct(
        private CreateVideo $command,
    ) {
    }

    public function command(): CreateVideo
    {
        return $this->command;
    }
}
