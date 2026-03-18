<?php

namespace App\Tests\Application\QueryHandler;

use App\Application\DTO\PaginatedResult;
use App\Application\Query\GetVideoListQuery;
use App\Application\QueryHandler\GetVideoListHandler;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Tests\Domain\Entity\VideoFake;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class GetVideoListHandlerTest extends TestCase
{
    public function testHandleReturnsCorrectResponse()
    {
        $video1 = new VideoFake();
        $video2 = new VideoFake();
        $videos = [$video1, $video2];
        $total = 2;
        $page = 1;
        $limit = 10;
        $paginatedResult = new PaginatedResult($videos, $total);

        $repo = $this->createMock(VideoRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('findAllPaginated')
            ->with($page, $limit)
            ->willReturn($paginatedResult);

        $request = new Request(['page' => $page, 'limit' => $limit]);
        $query = new GetVideoListQuery($request);
        $handler = new GetVideoListHandler($repo);
        $response = $handler($query);

        $this->assertEquals($total, $response->total);
        $this->assertEquals($page, $response->page);
        $this->assertEquals($limit, $response->limit);
    }
}
