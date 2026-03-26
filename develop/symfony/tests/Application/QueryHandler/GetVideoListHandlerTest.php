<?php

namespace App\Tests\Application\QueryHandler;

use App\Application\Query\GetVideoListQuery;
use App\Application\QueryHandler\GetVideoListHandler;
use App\Domain\Video\DTO\PaginatedResult;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Tests\Domain\Entity\VideoFake;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use App\Domain\Shared\ValueObject\Uuid;

class GetVideoListHandlerTest extends TestCase
{
    public function testHandleReturnsCorrectResponse()
    {
        $video1 = VideoFake::create();
        $video2 = VideoFake::create();
        $videos = [$video1, $video2];
        $total = 2;
        $page = 1;
        $limit = 10;
        $userId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $paginatedResult = new PaginatedResult($videos, $total);

        $repo = $this->createMock(VideoRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('findAllPaginated')
            ->with($page, $limit, $userId)
            ->willReturn($paginatedResult);

        $request = new Request(['page' => $page, 'limit' => $limit]);
        $query = new GetVideoListQuery($request, $userId);
        $handler = new GetVideoListHandler($repo, $this->createStub(StorageInterface::class));
        $response = $handler($query);

        $this->assertEquals($total, $response->total);
        $this->assertEquals($page, $response->page);
        $this->assertEquals($limit, $response->limit);
    }

    private function initializeUserId(GetVideoListQuery $query, Uuid $userId): void
    {
        $property = new \ReflectionProperty(GetVideoListQuery::class, 'userId');
        $property->setValue($query, $userId);
    }
}
