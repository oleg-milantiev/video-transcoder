<?php

namespace App\Domain\Video\Exception;

final class VideoMetadataExtractionFailed extends \RuntimeException
{
    public static function fromVideoId(string $videoId, string $reason): self
    {
        return new self(sprintf('Failed to extract metadata for video %s: %s', $videoId, $reason));
    }
}
