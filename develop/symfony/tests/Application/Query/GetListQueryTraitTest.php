<?php

namespace App\Tests\Application\Query;

use App\Application\Query\GetListQueryTrait;
use App\Application\Exception\QueryException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\UuidV4;

class DummyListQuery
{
    use GetListQueryTrait;
    protected const int DEFAULT_LIMIT = 10;
    protected const int MAX_LIMIT = 100;
    protected const int MAX_PAGE = 50;
    public int $page;
    public int $limit;
    public UuidV4 $userId;
}

class GetListQueryTraitTest extends TestCase
{
    public function testValidConstruction()
    {
        $request = new Request(['page' => 2, 'limit' => 20]);
        $query = new DummyListQuery($request, UuidV4::fromString('11111111-1111-4111-8111-111111111111'));
        $this->assertEquals(2, $query->page);
        $this->assertEquals(20, $query->limit);
    }

    public function testDefaultValues()
    {
        $request = new Request([]);
        $query = new DummyListQuery($request, UuidV4::fromString('11111111-1111-4111-8111-111111111111'));
        $this->assertEquals(1, $query->page);
        $this->assertEquals(10, $query->limit);
    }

    public function testInvalidPageThrowsException()
    {
        $this->expectException(QueryException::class);
        $request = new Request(['page' => 0, 'limit' => 5]);
        new DummyListQuery($request, UuidV4::fromString('11111111-1111-4111-8111-111111111111'));
    }

    public function testInvalidLimitThrowsException()
    {
        $this->expectException(QueryException::class);
        $request = new Request(['page' => 1, 'limit' => 101]);
        new DummyListQuery($request, UuidV4::fromString('11111111-1111-4111-8111-111111111111'));
    }
}

