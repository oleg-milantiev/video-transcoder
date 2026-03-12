<?php

namespace App\Domain\Video\Event;

use App\Domain\Video\Entity\Video;

final readonly class VideoMetadataExtractionStarted
{
    public function __construct(
        private Video $video
    ) {
    }

    public function video(): Video
    {
        return $this->video;
    }
}
