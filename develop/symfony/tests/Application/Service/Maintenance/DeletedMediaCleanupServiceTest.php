<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Maintenance;

use App\Application\Logging\LogServiceInterface;
use App\Application\Service\Maintenance\DeletedMediaCleanupService;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV4;

final class DeletedMediaCleanupServiceTest extends TestCase
{
    public function testCleanupDeletesFilesAndMarksMeta(): void
    {
        $videoId = UuidV4::fromString('11111111-1111-4111-8111-111111111111');
        $userId = UuidV4::fromString('22222222-2222-4222-8222-222222222222');
        $taskId = UuidV4::fromString('33333333-3333-4333-8333-333333333333');

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->once())
            ->method('findDeletedVideoForCleanup')
            ->with(100)
            ->willReturn([
                [
                    'videoId' => $videoId,
                    'userId' => $userId,
                    'sourcePath' => $videoId->toRfc4122() . '.mp4',
                ],
            ]);
        $videoRepository->expects($this->once())
            ->method('markVideoSourceFileMissing')
            ->with($videoId);

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())
            ->method('findDeletedTaskForCleanup')
            ->with(100)
            ->willReturn([
                [
                    'taskId' => $taskId,
                    'userId' => $userId,
                    'videoId' => $videoId,
                    'outputPath' => 'output/transcoded.mp4',
                ],
            ]);
        $taskRepository->expects($this->once())
            ->method('markTaskOutputFileMissing')
            ->with($taskId);

        $storage = $this->createMock(StorageInterface::class);
        $storage->expects($this->exactly(2))
            ->method('delete')
            ->willReturnMap([
                [$videoId->toRfc4122() . '.mp4', true],
                ['output/transcoded.mp4', false],
            ]);

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->exactly(2))->method('log');

        $service = new DeletedMediaCleanupService($videoRepository, $taskRepository, $storage, $logService);
        $result = $service->cleanup();

        $this->assertSame([
            'videoCandidates' => 1,
            'taskCandidates' => 1,
            'videoFilesDeleted' => 1,
            'taskFilesDeleted' => 0,
        ], $result);
    }
}
