<?php

namespace App\Domain\Video\Repository;

use App\Application\DTO\PaginatedResult;
use App\Domain\Video\Entity\Video;

interface PaginatedRepositoryInterface
{
    public function findAllPaginated(int $page, int $limit): PaginatedResult;
}
