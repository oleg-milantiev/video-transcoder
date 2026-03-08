<?php

namespace App\Domain\Video\Event;

use Symfony\Component\Uid\Uuid;

final readonly class VideoPreviewGenerationStarted
{
    public function __construct(
        private Uuid $videoId
    ) {
    }

    public function videoId(): Uuid
    {
        return $this->videoId;
    }
}
