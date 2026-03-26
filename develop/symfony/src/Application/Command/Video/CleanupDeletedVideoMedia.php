<?php

namespace App\Application\Command\Video;

use App\Domain\Shared\ValueObject\Uuid;

final readonly class CleanupDeletedVideoMedia
{
    public function __construct(public Uuid $videoId)
    {
    }
}
