<?php

namespace App\Domain\Video\Repository;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Video;

interface VideoRepositoryInterface extends PaginatedRepositoryInterface
{
    public function save(Video $video): Video;
    public function findById(Uuid $id): ?Video;

    /**
     * @return array<int, Video>
     */
    public function findDeletedVideoForCleanup(): array;
}
