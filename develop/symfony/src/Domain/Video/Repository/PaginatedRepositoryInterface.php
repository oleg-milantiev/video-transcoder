<?php

namespace App\Domain\Video\Repository;

use App\Domain\Video\DTO\PaginatedResult;

interface PaginatedRepositoryInterface
{
    public function findAllPaginated(int $page, int $limit): PaginatedResult;
}
