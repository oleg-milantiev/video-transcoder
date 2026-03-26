<?php

namespace App\Application\Query;

use App\Domain\Shared\ValueObject\Uuid;

use App\Application\Exception\QueryException;

final readonly class GetVideoDetailsQuery
{
    public Uuid $uuid;

    public function __construct(string $uuid)
    {
        try {
            $this->uuid = Uuid::fromString($uuid);
        } catch (\Throwable $e) {
            throw new QueryException('Invalid UUID');
        }
    }
}
