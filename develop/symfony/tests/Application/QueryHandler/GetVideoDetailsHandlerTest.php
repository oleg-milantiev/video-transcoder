<?php

namespace App\Tests\Application\QueryHandler;

use App\Application\Query\GetVideoDetailsQuery;
use App\Application\QueryHandler\GetVideoDetailsHandler;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Tests\Domain\Entity\VideoFake;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class GetVideoDetailsHandlerTest extends TestCase
{
    public function testConvertsNumericStatusIntoEnumName(): void
    {
        $video = new VideoFake();

        $repository = $this->createMock(VideoRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with($video->id())
            ->willReturn($video);
        $repository->expects($this->once())
            ->method('getDetails')
            ->with($video)
            ->willReturn([
                [
                    'id' => 1,
                    'name' => 'HD',
                    'task' => [
                        'id' => 42,
                        'status' => 2,
                        'progress' => 75,
                        'createdAt' => '2024-03-18 10:00',
                    ],
                ],
            ]);

        $user = new UserEntity();
        $user->id = $video->userId();

        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $handler = new GetVideoDetailsHandler($repository, $security);
        $query = new GetVideoDetailsQuery($video->id()->toRfc4122());
        $dto = $handler($query);

        $this->assertSame('PROCESSING', $dto->presetsWithTasks[0]->task->status);
        $this->assertSame(42, $dto->presetsWithTasks[0]->task->id);
    }
}
