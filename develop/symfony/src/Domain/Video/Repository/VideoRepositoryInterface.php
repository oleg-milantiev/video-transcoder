<?php

namespace App\Domain\Video\Repository;

use App\Domain\Video\Entity\Video;
use Symfony\Component\Uid\UuidV4;

interface VideoRepositoryInterface extends PaginatedRepositoryInterface
{
    public function save(Video $video): Video;
    public function findById(UuidV4 $id): ?Video;

    /**
     * @return array<int, Video>
     */
    public function findDeletedVideoForCleanup(int $limit = 100): array;
}
