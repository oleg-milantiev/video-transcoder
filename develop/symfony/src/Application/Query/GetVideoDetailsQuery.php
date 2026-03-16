<?php

namespace App\Application\Query;

use Symfony\Component\Uid\UuidV4;

use App\Application\Exception\QueryException;

final readonly class GetVideoDetailsQuery
{
    public UuidV4 $uuid;

    public function __construct(string $uuid)
    {
        try {
            $this->uuid = UuidV4::fromString($uuid);
        } catch (\Throwable $e) {
            throw new QueryException('Invalid UUID');
        }
    }
}
