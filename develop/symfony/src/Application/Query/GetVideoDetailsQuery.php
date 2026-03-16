<?php

namespace App\Application\Query;

use Symfony\Component\Uid\UuidV4;

final readonly class GetVideoDetailsQuery
{
    public function __construct(
        public UuidV4 $uuid,
    ) {}
}
