<?php

namespace App\Domain\Video\Repository;

use App\Domain\Video\DTO\PaginatedResult;
use Symfony\Component\Uid\UuidV4;

interface PaginatedRepositoryInterface
{
    public function findAllPaginated(int $page, int $limit, UuidV4 $userId): PaginatedResult;
}
