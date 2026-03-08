<?php

namespace App\Application\Command\Video;

use Symfony\Component\Uid\Uuid;

final readonly class CreateVideoPreview
{
    public function __construct(
        private Uuid $videoId
    ) {
    }

    public function getVideoId(): Uuid
    {
        return $this->videoId;
    }
}
