<?php
declare(strict_types=1);

namespace App\Domain\Video\Exception;

final class VideoMetadataInvalid extends \DomainException
{
    public static function missingDuration(): self
    {
        return new self('Video metadata is missing duration information.');
    }

    public static function durationExceedsLimit(float $duration, int $maxDuration): self
    {
        return new self(sprintf(
            'Video duration %.1f seconds exceeds your tariff limit of %d seconds.',
            $duration,
            $maxDuration
        ));
    }

    public static function missingResolution(): self
    {
        return new self('Video metadata is missing resolution (width/height) information.');
    }

    public static function resolutionExceedsLimit(int $width, int $height, int $maxWidth, int $maxHeight): self
    {
        return new self(sprintf(
            'Video resolution %dx%d exceeds your tariff limit of %dx%d.',
            $width,
            $height,
            $maxWidth,
            $maxHeight
        ));
    }
}
