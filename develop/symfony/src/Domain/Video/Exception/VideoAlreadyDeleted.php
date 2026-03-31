<?php
declare(strict_types=1);

namespace App\Domain\Video\Exception;

final class VideoAlreadyDeleted extends \DomainException
{
    public static function forVideo(): self
    {
        return new self('Video is already deleted.');
    }
}
