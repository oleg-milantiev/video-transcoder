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
use App\Domain\Shared\ValueObject\Uuid;

final class DeletedTaskCleanupServiceTest extends TestCase
{
    public function testCleanupByVideoIdProcessesOnlyDeletedTasks(): void
    {
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $presetId = Uuid::fromString('22222222-2222-4222-8222-222222222222');
        $userId = Uuid::fromString('33333333-3333-4333-8333-333333333333');

        $deletedTask = Task::reconstitute(
            videoId: $videoId,
            presetId: $presetId,
            userId: $userId,
            status: TaskStatus::deleted(),
            progress: new Progress(100),
            dates: TaskDates::create(),
            id: Uuid::fromString('44444444-4444-4444-8444-444444444444'),
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
            id: Uuid::fromString('55555555-5555-4555-8555-555555555555'),
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

    public function testCleanupProcessesDeletedCandidatesFromRepository(): void
    {
        $videoId = Uuid::fromString('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
        $presetId = Uuid::fromString('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb');
        $userId = Uuid::fromString('cccccccc-cccc-4ccc-8ccc-cccccccccccc');

        $task = Task::reconstitute(
            videoId: $videoId,
            presetId: $presetId,
            userId: $userId,
            status: TaskStatus::deleted(),
            progress: new Progress(100),
            dates: TaskDates::create(),
            id: Uuid::fromString('dddddddd-dddd-4ddd-8ddd-dddddddddddd'),
            meta: ['output' => 'output/deleted.mp4'],
            deleted: true,
        );

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())
            ->method('findDeletedTaskForCleanup')
            ->willReturn([$task]);
        $taskRepository->expects($this->once())->method('save');

        $storage = $this->createMock(StorageInterface::class);
        $storage->expects($this->once())->method('delete')->with('output/deleted.mp4')->willReturn(true);

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())->method('log');

        $service = new DeletedTaskCleanupService($taskRepository, $storage, $logService);
        $result = $service->cleanup();

        $this->assertSame(['candidates' => 1, 'filesDeleted' => 1], $result);
    }

    public function testCleanupTaskReturnsFalseWhenNoOutputKey(): void
    {
        $task = Task::reconstitute(
            videoId: Uuid::fromString('11111111-1111-4111-8111-111111111111'),
            presetId: Uuid::fromString('22222222-2222-4222-8222-222222222222'),
            userId: Uuid::fromString('33333333-3333-4333-8333-333333333333'),
            status: TaskStatus::deleted(),
            progress: new Progress(0),
            dates: TaskDates::create(),
            id: Uuid::fromString('44444444-4444-4444-8444-444444444444'),
            meta: [],  // no 'output' key
            deleted: true,
        );

        $storage = $this->createMock(StorageInterface::class);
        $storage->expects($this->never())->method('delete');

        $service = new DeletedTaskCleanupService(
            $this->createStub(TaskRepositoryInterface::class),
            $storage,
            $this->createStub(LogServiceInterface::class),
        );

        $result = $service->cleanupTask($task);
        $this->assertFalse($result);
    }

    public function testCleanupTaskReturnsFalseWhenOutputKeyIsEmpty(): void
    {
        $task = Task::reconstitute(
            videoId: Uuid::fromString('11111111-1111-4111-8111-111111111111'),
            presetId: Uuid::fromString('22222222-2222-4222-8222-222222222222'),
            userId: Uuid::fromString('33333333-3333-4333-8333-333333333333'),
            status: TaskStatus::deleted(),
            progress: new Progress(0),
            dates: TaskDates::create(),
            id: Uuid::fromString('44444444-4444-4444-8444-444444444444'),
            meta: ['output' => ''],  // empty output key
            deleted: true,
        );

        $storage = $this->createMock(StorageInterface::class);
        $storage->expects($this->never())->method('delete');

        $service = new DeletedTaskCleanupService(
            $this->createStub(TaskRepositoryInterface::class),
            $storage,
            $this->createStub(LogServiceInterface::class),
        );

        $result = $service->cleanupTask($task);
        $this->assertFalse($result);
    }

    public function testCleanupTaskReturnsTrueWhenStorageDeletesFile(): void
    {
        $outputKey = 'output/success.mp4';
        $task = Task::reconstitute(
            videoId: Uuid::fromString('11111111-1111-4111-8111-111111111111'),
            presetId: Uuid::fromString('22222222-2222-4222-8222-222222222222'),
            userId: Uuid::fromString('33333333-3333-4333-8333-333333333333'),
            status: TaskStatus::deleted(),
            progress: new Progress(0),
            dates: TaskDates::create(),
            id: Uuid::fromString('44444444-4444-4444-8444-444444444444'),
            meta: ['output' => $outputKey],
            deleted: true,
        );

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())->method('save');

        $storage = $this->createMock(StorageInterface::class);
        $storage->expects($this->once())
            ->method('delete')
            ->with($outputKey)
            ->willReturn(true);

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())->method('log');

        $service = new DeletedTaskCleanupService($taskRepository, $storage, $logService);

        $result = $service->cleanupTask($task);
        $this->assertTrue($result);
    }
}

