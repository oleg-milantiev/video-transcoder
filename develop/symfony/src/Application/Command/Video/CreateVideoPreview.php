<?php

namespace App\Application\Command\Video;

use App\Domain\Video\Entity\Video;

final readonly class CreateVideoPreview
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
