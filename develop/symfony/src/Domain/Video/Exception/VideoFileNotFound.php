<?php

namespace App\Domain\Video\Exception;

final class VideoFileNotFound extends \DomainException
{
    public static function cannotDetermineSize(string $filePath): self
    {
        return new self(sprintf('Cannot determine file size for: %s', $filePath));
    }
}
