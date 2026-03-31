<?php
declare(strict_types=1);

namespace App\Domain\Video\Exception;

use DomainException;

final class VideoPreviewGenerationFailed extends DomainException
{
    public static function fromVideoId(string $videoId, string $reason): self
    {
        return new self(sprintf('Failed to generate preview for video "%s": %s', $videoId, $reason));
    }
}
