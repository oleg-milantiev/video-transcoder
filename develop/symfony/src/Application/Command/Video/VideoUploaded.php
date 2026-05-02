<?php
declare(strict_types=1);

namespace App\Application\Command\Video;

use App\Domain\Video\Entity\Video;

// todo когда перейду в s3, эту команду надо делать async. Чтобы качала гигабайты в S3
final readonly class VideoUploaded
{
    public function __construct(
        private Video $video,
        private string $filePath,
        private string $filename,
    ) {
    }

    public function video(): Video
    {
        return $this->video;
    }

    public function filePath(): string
    {
        return $this->filePath;
    }

    public function filename(): string
    {
        return $this->filename;
    }
}
