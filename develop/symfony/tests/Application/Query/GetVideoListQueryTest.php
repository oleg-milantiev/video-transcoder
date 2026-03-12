<?php

namespace App\Tests\Application\Query;

use App\Application\Query\GetVideoListQuery;
use App\Application\Exception\QueryException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class GetVideoListQueryTest extends TestCase
{
    public function testValidConstruction()
    {
        $request = new Request(['page' => 3, 'limit' => 15]);
        $query = new GetVideoListQuery($request);
        $this->assertEquals(3, $query->page);
        $this->assertEquals(15, $query->limit);
    }

    public function testDefaultValues()
    {
        $request = new Request([]);
        $query = new GetVideoListQuery($request);
        $this->assertEquals(1, $query->page);
        $this->assertEquals(10, $query->limit);
    }

    public function testInvalidPageThrowsException()
    {
        $this->expectException(QueryException::class);
        $request = new Request(['page' => 0, 'limit' => 5]);
        new GetVideoListQuery($request);
    }

    public function testInvalidLimitThrowsException()
    {
        $this->expectException(QueryException::class);
        $request = new Request(['page' => 1, 'limit' => 0]);
        new GetVideoListQuery($request);
    }
}

