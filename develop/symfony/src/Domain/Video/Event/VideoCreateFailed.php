<?php

namespace App\Domain\Video\Event;

use App\Application\Command\Video\CreateVideo;
use App\Domain\Video\Entity\Video;

final readonly class VideoCreateFailed
{
    public function __construct(
        private CreateVideo $command,
    ) {
    }

    public function video(): Video
    {
        return $this->video;
    }
}
