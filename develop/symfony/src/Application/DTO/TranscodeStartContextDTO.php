<?php
declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Entity\Video;

readonly class TranscodeStartContextDTO
{
    public function __construct(
        public Task $task,
        public Video $video,
        public Preset $preset,
        public string $relativeOutputPath,
        public string $absoluteOutputPath,
        public string $inputPath,
    ) {
    }
}

