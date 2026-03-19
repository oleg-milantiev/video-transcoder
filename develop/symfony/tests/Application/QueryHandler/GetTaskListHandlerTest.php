<?php

namespace App\Tests\Application\QueryHandler;

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
        $video1 = new VideoFake();
        $video2 = new VideoFake();
        $preset1 = new PresetFake();
        $preset2 = new PresetFake();

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

        $videoRepo = $this->createMock(VideoRepositoryInterface::class);
        $videoRepo->expects($this->exactly(2))
            ->method('findById')
            ->willReturnCallback(function ($videoId) use ($task1, $task2, $video1, $video2) {
                if ($videoId->equals($task1->videoId())) {
                    return $video1;
                }

                if ($videoId->equals($task2->videoId())) {
                    return $video2;
                }

                return null;
            });

        $presetRepo = $this->createMock(PresetRepositoryInterface::class);
        $presetRepo->expects($this->exactly(2))
            ->method('findById')
            ->willReturnCallback(function (int $presetId) use ($task1, $task2, $preset1, $preset2) {
                if ($presetId === $task1->presetId()) {
                    return $preset1;
                }

                if ($presetId === $task2->presetId()) {
                    return $preset2;
                }

                return null;
            });

        $request = new Request(['page' => $page, 'limit' => $limit]);
        $query = new GetTaskListQuery($request);
        $handler = new GetTaskListHandler($repo, $videoRepo, $presetRepo);
        $response = $handler($query);

        $this->assertEquals($total, $response->total);
        $this->assertEquals($page, $response->page);
        $this->assertEquals($limit, $response->limit);
        $this->assertSame(1, $response->totalPages);
        $this->assertCount(2, $response->items);

        $this->assertSame($task1->id(), $response->items[0]->id);
        $this->assertSame($video1->title()->value(), $response->items[0]->videoTitle);
        $this->assertSame($preset1->title()->value(), $response->items[0]->presetTitle);
        $this->assertSame($task1->status()->name, $response->items[0]->status);
        $this->assertSame($task1->progress()->value(), $response->items[0]->progress);
        $this->assertSame($task1->createdAt()->format('Y-m-d H:i'), $response->items[0]->createdAt);

        $this->assertSame($task2->id(), $response->items[1]->id);
        $this->assertSame($video2->title()->value(), $response->items[1]->videoTitle);
        $this->assertSame($preset2->title()->value(), $response->items[1]->presetTitle);
        $this->assertSame($task2->status()->name, $response->items[1]->status);
        $this->assertSame($task2->progress()->value(), $response->items[1]->progress);
        $this->assertSame($task2->createdAt()->format('Y-m-d H:i'), $response->items[1]->createdAt);
    }
}
