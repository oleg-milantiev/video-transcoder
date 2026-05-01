<?php
declare(strict_types=1);

namespace App\Application\Query;

final readonly class GetStorageQuery
{
    public function __construct(
        public string $key,
    ) {
    }
}
