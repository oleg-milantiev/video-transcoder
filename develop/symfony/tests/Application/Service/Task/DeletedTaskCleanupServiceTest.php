<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Task;

use App\Application\Logging\LogServiceInterface;
use App\Application\Service\Task\DeletedTaskCleanupService;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskDates;
use App\Domain\Video\ValueObject\TaskStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV4;

final class DeletedTaskCleanupServiceTest extends TestCase
{
    public function testCleanupByVideoIdProcessesOnlyDeletedTasks(): void
    {
        $videoId = UuidV4::fromString('11111111-1111-4111-8111-111111111111');
        $presetId = UuidV4::fromString('22222222-2222-4222-8222-222222222222');
        $userId = UuidV4::fromString('33333333-3333-4333-8333-333333333333');

        $deletedTask = Task::reconstitute(
            videoId: $videoId,
            presetId: $presetId,
            userId: $userId,
            status: TaskStatus::deleted(),
            progress: new Progress(100),
            dates: TaskDates::create(),
            id: UuidV4::fromString('44444444-4444-4444-8444-444444444444'),
            meta: ['output' => 'output/file.mp4'],
            deleted: true,
        );

        $activeTask = Task::reconstitute(
            videoId: $videoId,
            presetId: $presetId,
            userId: $userId,
            status: TaskStatus::pending(),
            progress: new Progress(0),
            dates: TaskDates::create(),
            id: UuidV4::fromString('55555555-5555-4555-8555-555555555555'),
            meta: ['output' => 'output/skip.mp4'],
            deleted: false,
        );

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())
            ->method('findByVideoId')
            ->with($videoId)
            ->willReturn([$deletedTask, $activeTask]);

        $storage = $this->createMock(StorageInterface::class);
        $storage->expects($this->once())
            ->method('delete')
            ->with('output/file.mp4')
            ->willReturn(false);

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())->method('log');

        $service = new DeletedTaskCleanupService($taskRepository, $storage, $logService);

        $result = $service->cleanupByVideoId($videoId);

        $this->assertSame(['candidates' => 1, 'filesDeleted' => 0], $result);
    }
}

