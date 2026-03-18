<?php

declare(strict_types=1);

namespace App\Tests\Application\Query;

use App\Application\Exception\QueryException;
use App\Application\Query\GetVideoDetailsQuery;
use PHPUnit\Framework\TestCase;

class GetVideoDetailsQueryTest extends TestCase
{
    public function testAcceptsValidUuidString(): void
    {
        $uuid = '123e4567-e89b-42d3-a456-426614174000';
        $query = new GetVideoDetailsQuery($uuid);

        $this->assertSame($uuid, $query->uuid->toRfc4122());
    }

    public function testInvalidUuidThrowsQueryException(): void
    {
        $this->expectException(QueryException::class);
        new GetVideoDetailsQuery('not-a-uuid');
    }
}

