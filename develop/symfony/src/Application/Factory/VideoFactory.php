<?php
declare(strict_types=1);

namespace App\Application\Factory;

use App\Application\Command\Video\CreateVideo;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoTitle;

final readonly class VideoFactory
{
    public function fromFilename(string $filename, Uuid $userId): Video
    {
        return Video::create(
            title: new VideoTitle(pathinfo($filename, PATHINFO_FILENAME)),
            extension: new FileExtension(pathinfo($filename, PATHINFO_EXTENSION)),
            userId: $userId,
        );
    }
}
