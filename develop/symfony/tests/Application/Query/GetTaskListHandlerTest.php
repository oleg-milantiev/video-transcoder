<?php

namespace App\Tests\Application\Query;

use App\Application\DTO\PaginatedResult;
use App\Application\Query\GetTaskListHandler;
use App\Application\Query\GetTaskListQuery;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Tests\Domain\Entity\TaskFake;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class GetTaskListHandlerTest extends TestCase
{
    public function testHandleReturnsCorrectResponse()
    {
        $task1 = new TaskFake();
        $task2 = new TaskFake();
        $tasks = [$task1, $task2];
        $total = 2;
        $page = 1;
        $limit = 10;
        $paginatedResult = new PaginatedResult($tasks, $total);

        $repo = $this->createMock(TaskRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('findAllPaginated')
            ->with($page, $limit)
            ->willReturn($paginatedResult);

        $request = new Request(['page' => $page, 'limit' => $limit]);
        $query = new GetTaskListQuery($request);
        $handler = new GetTaskListHandler($repo);
        $response = $handler($query);

        $this->assertEquals($total, $response->total);
        $this->assertEquals($page, $response->page);
        $this->assertEquals($limit, $response->limit);
    }
}
