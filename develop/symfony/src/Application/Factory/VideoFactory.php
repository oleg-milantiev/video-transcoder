<?php
declare(strict_types=1);

namespace App\Application\Factory;

use App\Application\Command\Video\CreateVideo;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoTitle;

final readonly class VideoFactory
{
    public function fromCreateVideo(CreateVideo $command): Video
    {
        $name = $command->file()->details()['metadata']['originalName'] ?? $command->file()->getName();

        return Video::create(
            title: new VideoTitle(pathinfo((string) $name, PATHINFO_FILENAME)),
            extension: new FileExtension(pathinfo($command->file()->getName(), PATHINFO_EXTENSION)),
            userId: $command->userId(),
        );
    }
}
