<?php

namespace App\Domain\Video\Repository;

use App\Domain\Video\Entity\Video;


use Symfony\Component\Uid\Uuid;

interface VideoRepositoryInterface extends PaginatedRepositoryInterface
{
    public function save(Video $video): Video;
    public function findById(int $id): ?Video;
    public function log(Uuid $id, string $level, string $text): void;
}
