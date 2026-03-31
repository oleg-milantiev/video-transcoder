<?php
declare(strict_types=1);

namespace App\Domain\Video\Exception;

final class VideoSizeExceedsQuota extends \DomainException
{
    public static function fromSize(float $fileSizeMb, float $maxSizeMb): self
    {
        return new self(sprintf(
            'Video size %.1f MB exceeds your tariff limit of %.1f MB.',
            $fileSizeMb,
            $maxSizeMb
        ));
    }
}
