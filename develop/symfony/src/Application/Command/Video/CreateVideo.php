<?php

namespace App\Application\Command\Video;

use TusPhp\File;

final readonly class CreateVideo
{
    public function __construct(
        private File $file,
        private int $userId,
    ) {
    }

    public function file(): File
    {
        return $this->file;
    }
    public function userId(): int
    {
        return $this->userId;
    }
}
