<?php

namespace App\Tests\Application\QueryHandler;

use App\Application\Query\GetVideoDetailsQuery;
use App\Application\QueryHandler\GetVideoDetailsHandler;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Tests\Domain\Entity\VideoFake;
use PHPUnit\Framework\TestCase;

class GetVideoDetailsHandlerTest extends TestCase
{
    public function testConvertsNumericStatusIntoEnumName(): void
    {
        $video = new VideoFake();

        $repository = $this->createMock(VideoRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('getDetails')
            ->willReturn([
                'video' => $video,
                'presetsWithTasks' => [
                    [
                        'id' => 1,
                        'name' => 'HD',
                        'task' => [
                            'status' => 2,
                            'progress' => 75,
                            'createdAt' => '2024-03-18 10:00',
                        ],
                    ],
                ],
            ]);

        $handler = new GetVideoDetailsHandler($repository);
        $query = new GetVideoDetailsQuery($video->id()->toRfc4122());
        $dto = $handler($query);

        $this->assertSame('PROCESSING', $dto->presetsWithTasks[0]->task->status);
    }
}

