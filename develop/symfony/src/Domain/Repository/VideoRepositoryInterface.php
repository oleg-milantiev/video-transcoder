<?php

namespace App\Domain\Repository;

use App\Entity\Video;

interface VideoRepositoryInterface
{
    public function save(Video $video, bool $flush = false): void;
    public function remove(Video $video, bool $flush = false): void;
}
