<?php

namespace App\Tests\Application\Query;

use App\Application\Query\GetTaskListQuery;
use App\Application\Exception\QueryException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class GetTaskListQueryTest extends TestCase
{
    public function testValidConstruction()
    {
        $request = new Request(['page' => 2, 'limit' => 5]);
        $query = new GetTaskListQuery($request);
        $this->assertEquals(2, $query->page);
        $this->assertEquals(5, $query->limit);
    }

    public function testDefaultValues()
    {
        $request = new Request([]);
        $query = new GetTaskListQuery($request);
        $this->assertEquals(1, $query->page);
        $this->assertEquals(10, $query->limit);
    }

    public function testInvalidPageThrowsException()
    {
        $this->expectException(QueryException::class);
        $request = new Request(['page' => 0, 'limit' => 5]);
        new GetTaskListQuery($request);
    }

    public function testInvalidLimitThrowsException()
    {
        $this->expectException(QueryException::class);
        $request = new Request(['page' => 1, 'limit' => 0]);
        new GetTaskListQuery($request);
    }
}

