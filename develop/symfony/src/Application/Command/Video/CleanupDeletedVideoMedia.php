<?php
declare(strict_types=1);

namespace App\Application\Command\Video;

use App\Domain\Shared\ValueObject\Uuid;

final readonly class CleanupDeletedVideoMedia
{
    public function __construct(public Uuid $videoId)
    {
    }
}
