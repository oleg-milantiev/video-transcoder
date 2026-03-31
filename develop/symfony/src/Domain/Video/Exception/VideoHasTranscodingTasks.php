<?php
declare(strict_types=1);

namespace App\Domain\Video\Exception;

final class VideoHasTranscodingTasks extends \DomainException
{
    public static function forVideo(): self
    {
        return new self('Video has active transcoding tasks and cannot be deleted.');
    }
}
