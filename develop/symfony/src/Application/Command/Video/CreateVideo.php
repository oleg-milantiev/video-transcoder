<?php
declare(strict_types=1);

namespace App\Application\Command\Video;

use App\Domain\Shared\ValueObject\Uuid;
use TusPhp\File;

final readonly class CreateVideo
{
    public function __construct(
        private File $file,
        private Uuid $userId,
    ) {
    }

    public function file(): File
    {
        return $this->file;
    }
    public function userId(): Uuid
    {
        return $this->userId;
    }
}
