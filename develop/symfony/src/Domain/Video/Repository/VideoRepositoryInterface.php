<?php

namespace App\Domain\Video\Repository;

use App\Domain\Video\Entity\Video;

interface VideoRepositoryInterface
{
    public function save(Video $video): void;
    public function findById(int $id): ?Video;
}
