<?php

namespace App\Tests\Application\Query;

use App\Application\DTO\PaginatedResult;
use App\Application\Query\GetTaskListQuery;
use App\Application\QueryHandler\GetTaskListHandler;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Tests\Domain\Entity\PresetFake;
use App\Tests\Domain\Entity\TaskFake;
use App\Tests\Domain\Entity\VideoFake;
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

        $videoRepo = $this->createStub(VideoRepositoryInterface::class);
        $videoRepo->method('findById')->willReturnCallback(fn() => new VideoFake());
        $presetRepo = $this->createStub(PresetRepositoryInterface::class);
        $presetRepo->method('findById')->willReturnCallback(fn() => new PresetFake());

        $request = new Request(['page' => $page, 'limit' => $limit]);
        $query = new GetTaskListQuery($request);
        $handler = new GetTaskListHandler($repo, $videoRepo, $presetRepo);
        $response = $handler($query);

        $this->assertEquals($total, $response->total);
        $this->assertEquals($page, $response->page);
        $this->assertEquals($limit, $response->limit);
    }
}
