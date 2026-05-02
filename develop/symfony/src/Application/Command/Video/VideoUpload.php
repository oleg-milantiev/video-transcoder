<?php
declare(strict_types=1);

namespace App\Application\Command\Video;

use App\Domain\Video\Entity\Video;

final readonly class VideoUpload
{
    public function __construct(
        private Video $video,
        private string $url,
    ) {
    }

    public function video(): Video
    {
        return $this->video;
    }

    public function url(): string
    {
        return $this->url;
    }
}
