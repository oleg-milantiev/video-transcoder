<?php

namespace App\Application\Command\Video;

use Symfony\Component\Uid\UuidV4;

final readonly class CleanupDeletedVideoMedia
{
    public function __construct(public UuidV4 $videoId)
    {
    }
}
