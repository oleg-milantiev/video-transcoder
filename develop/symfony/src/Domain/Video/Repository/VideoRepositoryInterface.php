<?php

namespace App\Domain\Video\Repository;

use App\Domain\Video\Entity\Video;

interface VideoRepositoryInterface
{
    public function save(Video $video, bool $flush = false): void;
    public function remove(Video $video, bool $flush = false): void;
}
