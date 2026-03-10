<?php

namespace App\Domain\Video\Repository;

use App\Application\DTO\PaginatedResult;
use App\Domain\Video\Entity\Video;

interface VideoRepositoryInterface
{
    public function save(Video $video): void;
    public function findById(int $id): ?Video;
    public function findAllPaginated(int $page, int $limit): PaginatedResult;
}
