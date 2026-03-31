<?php
declare(strict_types=1);

namespace App\Domain\Video\Repository;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\DTO\PaginatedResult;

interface PaginatedRepositoryInterface
{
    public function findAllPaginated(int $page, int $limit, Uuid $userId): PaginatedResult;
}
