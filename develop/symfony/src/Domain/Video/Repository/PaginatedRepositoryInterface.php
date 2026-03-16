<?php

namespace App\Domain\Video\Repository;

use App\Application\DTO\PaginatedResult;

interface PaginatedRepositoryInterface
{
    public function findAllPaginated(int $page, int $limit): PaginatedResult;
}
