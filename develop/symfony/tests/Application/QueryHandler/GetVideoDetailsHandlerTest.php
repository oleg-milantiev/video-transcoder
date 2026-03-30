<?php

namespace App\Tests\Application\QueryHandler;

use App\Application\Exception\QueryException;
use App\Application\Query\GetVideoDetailsQuery;
use App\Application\QueryHandler\GetVideoDetailsHandler;
use App\Application\Query\Repository\VideoDetailsReadRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Security\Voter\VideoAccessVoter;
use App\Tests\Domain\Entity\VideoFake;
use App\Tests\Infrastructure\Persistence\Doctrine\Entity\TariffFake;
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
                        'status' => 3,
                        'progress' => 75,
                        'createdAt' => '2024-03-18 10:00',
                        'downloadFilename' => 'HD - example.mp4',
                    ],
                ],
            ]);

        $user = new UserEntity();
        $user->tariff = TariffFake::create();
        $user->tariff->storageHour = 24;

        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('isGranted')
            ->with(VideoAccessVoter::CAN_VIEW_DETAILS, $video)
            ->willReturn(true);
        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $handler = new GetVideoDetailsHandler($repository, $videoDetailsRepository, $this->createStub(StorageInterface::class), $security);
        $query = new GetVideoDetailsQuery($video->id()->toRfc4122());
        $dto = $handler($query);

        $this->assertSame('PROCESSING', $dto->presetsWithTasks[0]->task->status);
        $this->assertSame('42424242-4242-4242-8242-424242424242', $dto->presetsWithTasks[0]->task->id);
    }

    public function testThrowsWhenVideoNotFound(): void
    {
        $repository = $this->createStub(VideoRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $videoDetailsRepository = $this->createStub(VideoDetailsReadRepositoryInterface::class);
        $security = $this->createStub(Security::class);

        $handler = new GetVideoDetailsHandler($repository, $videoDetailsRepository, $this->createStub(StorageInterface::class), $security);

        $this->expectException(QueryException::class);
        $handler(new GetVideoDetailsQuery('00000000-0000-4000-8000-000000000101'));
    }

    public function testThrowsWhenAccessDenied(): void
    {
        $video = VideoFake::create();

        $repository = $this->createStub(VideoRepositoryInterface::class);
        $repository->method('findById')->willReturn($video);

        $videoDetailsRepository = $this->createStub(VideoDetailsReadRepositoryInterface::class);

        $security = $this->createMock(Security::class);
        $security->expects($this->once())->method('isGranted')->with(
            VideoAccessVoter::CAN_VIEW_DETAILS,
            $video
        )->willReturn(false);

        $handler = new GetVideoDetailsHandler($repository, $videoDetailsRepository, $this->createStub(StorageInterface::class), $security);

        $this->expectException(QueryException::class);
        $handler(new GetVideoDetailsQuery($video->id()->toRfc4122()));
    }
}
