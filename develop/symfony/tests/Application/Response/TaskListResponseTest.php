<?php

declare(strict_types=1);

namespace App\Tests\Application\Response;

use App\Application\Response\TaskListResponse;
use App\Tests\Domain\Entity\PresetFake;
use App\Tests\Domain\Entity\TaskFake;
use App\Tests\Domain\Entity\VideoFake;
use PHPUnit\Framework\TestCase;

class TaskListResponseTest extends TestCase
{
    public function testFromDomainBuildsDtosAndPagination(): void
    {
        $task = new TaskFake();
        $video = new VideoFake();
        $preset = new PresetFake();

        $response = TaskListResponse::fromDomain(
            items: [
                [
                    'task' => $task,
                    'video' => $video,
                    'preset' => $preset,
                ],
            ],
            total: 4,
            page: 1,
            limit: 2,
        );

        $this->assertCount(1, $response->items);
        $this->assertSame(4, $response->total);
        $this->assertSame(1, $response->page);
        $this->assertSame(2, $response->limit);
        $this->assertSame(2, $response->totalPages);
        $this->assertSame($task->id()->toRfc4122(), $response->items[0]->id);
        $this->assertSame($video->title()->value(), $response->items[0]->videoTitle);
    }

    public function testFromDomainHandlesEmptyCollection(): void
    {
        $response = TaskListResponse::fromDomain([], total: 0, page: 1, limit: 5);

        $this->assertSame([], $response->items);
        $this->assertSame(0, $response->totalPages);
    }
}
