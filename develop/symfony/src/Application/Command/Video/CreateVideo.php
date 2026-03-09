<?php

namespace App\Application\Command\Video;

use TusPhp\File;

final readonly class CreateVideo
{
    public function __construct(
        private File $file,
    ) {
    }

    public function getFile(): File
    {
        return $this->file;
    }
}
