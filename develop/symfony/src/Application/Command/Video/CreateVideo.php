<?php

namespace App\Application\Command\Video;

use TusPhp\File;
use Symfony\Component\Uid\UuidV4;

final readonly class CreateVideo
{
    public function __construct(
        private File $file,
        private UuidV4 $userId,
    ) {
    }

    public function file(): File
    {
        return $this->file;
    }
    public function userId(): UuidV4
    {
        return $this->userId;
    }
}
