<?php

namespace App\Tests\Application\QueryHandler;

use App\Application\Query\GetVideoDetailsQuery;
use App\Application\QueryHandler\GetVideoDetailsHandler;
use App\Application\Query\Repository\VideoDetailsReadRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Infrastructure\Security\Voter\VideoAccessVoter;
use App\Tests\Domain\Entity\VideoFake;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class GetVideoDetailsHandlerTest extends TestCase
{
    public function testConvertsNumericStatusIntoEnumName(): void
    {
        $video = VideoFake::create();

        $repository = $this->createMock(VideoRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with($video->id())
            ->willReturn($video);
        $videoDetailsRepository = $this->createMock(VideoDetailsReadRepositoryInterface::class);
        $videoDetailsRepository->expects($this->once())
            ->method('getDetailsByVideoId')
            ->with($video->id())
            ->willReturn([
                [
                    'id' => '11111111-1111-4111-8111-111111111111',
                    'title' => 'HD',
                    'task' => [
                        'id' => '42424242-4242-4242-8242-424242424242',
                        'status' => 2,
                        'progress' => 75,
                        'createdAt' => '2024-03-18 10:00',
                    ],
                ],
            ]);

        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('isGranted')
            ->with(VideoAccessVoter::CAN_VIEW_DETAILS, $video)
            ->willReturn(true);

        $handler = new GetVideoDetailsHandler($repository, $videoDetailsRepository, $security);
        $query = new GetVideoDetailsQuery($video->id()->toRfc4122());
        $dto = $handler($query);

        $this->assertSame('PROCESSING', $dto->presetsWithTasks[0]->task->status);
        $this->assertSame('42424242-4242-4242-8242-424242424242', $dto->presetsWithTasks[0]->task->id);
    }
}
