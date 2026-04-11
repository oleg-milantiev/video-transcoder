<?php

declare(strict_types=1);

namespace App\Tests\Application\QueryHandler;

use App\Application\Exception\TaskDownloadAccessDeniedException;
use App\Application\Exception\TaskNotFoundException;
use App\Application\Exception\VideoNotFoundException;
use App\Application\Logging\LogServiceInterface;
use App\Application\Query\TaskDownloadQuery;
use App\Application\QueryHandler\TaskDownloadHandler;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskDates;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Infrastructure\Security\Voter\VideoAccessVoter;
use App\Tests\Domain\Entity\TaskFake;
use App\Tests\Domain\Entity\VideoFake;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Bundle\SecurityBundle\Security;

final class TaskDownloadHandlerTest extends TestCase
{
    public function testThrowsTaskNotFoundWhenTaskIsNull(): void
    {
        $query = new TaskDownloadQuery(
            Uuid::generate()->toRfc4122(),
            Uuid::generate()->toRfc4122(),
        );

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn(null);

        $handler = new TaskDownloadHandler(
            $taskRepository,
            $this->createStub(VideoRepositoryInterface::class),
            $this->createStub(LogServiceInterface::class),
            $this->createStub(Security::class),
            $this->createStub(StorageInterface::class),
        );

        $this->expectException(TaskNotFoundException::class);
        $this->expectExceptionMessage('Task not found');
        $handler->__invoke($query);
    }

    public function testThrowsVideoNotFoundWhenVideoIsNull(): void
    {
        $task = TaskFake::create();
        $query = new TaskDownloadQuery(
            $task->id()->toRfc4122(),
            Uuid::generate()->toRfc4122(),
        );

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn($task);

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn(null);

        $handler = new TaskDownloadHandler(
            $taskRepository,
            $videoRepository,
            $this->createStub(LogServiceInterface::class),
            $this->createStub(Security::class),
            $this->createStub(StorageInterface::class),
        );

        $this->expectException(VideoNotFoundException::class);
        $this->expectExceptionMessage('Video not found');
        $handler->__invoke($query);
    }

    public function testThrowsAccessDeniedWhenNotGranted(): void
    {
        $task = TaskFake::create();
        $video = VideoFake::create();
        $query = new TaskDownloadQuery(
            $task->id()->toRfc4122(),
            Uuid::generate()->toRfc4122(),
        );

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn($task);

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn($video);

        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('isGranted')
            ->with(VideoAccessVoter::CAN_DOWNLOAD_TRANSCODE, $video)
            ->willReturn(false);

        $handler = new TaskDownloadHandler(
            $taskRepository,
            $videoRepository,
            $this->createStub(LogServiceInterface::class),
            $security,
            $this->createStub(StorageInterface::class),
        );

        $this->expectException(TaskDownloadAccessDeniedException::class);
        $this->expectExceptionMessage('Access denied');
        $handler->__invoke($query);
    }

    public function testThrowsTaskNotFoundWhenStatusIsNotCompleted(): void
    {
        // TaskFake produces a STARTING task — not COMPLETED
        $task = TaskFake::create();
        $video = VideoFake::create();
        $query = new TaskDownloadQuery(
            $task->id()->toRfc4122(),
            Uuid::generate()->toRfc4122(),
        );

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn($task);

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn($video);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(true);

        $handler = new TaskDownloadHandler(
            $taskRepository,
            $videoRepository,
            $this->createStub(LogServiceInterface::class),
            $security,
            $this->createStub(StorageInterface::class),
        );

        $this->expectException(TaskNotFoundException::class);
        $this->expectExceptionMessage('Task output is not ready');
        $handler->__invoke($query);
    }

    public function testThrowsTaskNotFoundWhenTaskIsDeleted(): void
    {
        // COMPLETED status but flagged deleted — passes status check, fails deleted check
        $task = Task::reconstitute(
            videoId: Uuid::fromString('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'),
            presetId: Uuid::fromString('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'),
            userId: Uuid::fromString('cccccccc-cccc-4ccc-8ccc-cccccccccccc'),
            status: TaskStatus::COMPLETED,
            progress: new Progress(100),
            dates: TaskDates::create(),
            id: Uuid::fromString('dddddddd-dddd-4ddd-8ddd-dddddddddddd'),
            meta: ['output' => 'some/output.mp4'],
            deleted: true,
        );

        $video = VideoFake::create();
        $query = new TaskDownloadQuery(
            $task->id()->toRfc4122(),
            Uuid::generate()->toRfc4122(),
        );

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn($task);

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn($video);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(true);

        $handler = new TaskDownloadHandler(
            $taskRepository,
            $videoRepository,
            $this->createStub(LogServiceInterface::class),
            $security,
            $this->createStub(StorageInterface::class),
        );

        $this->expectException(TaskNotFoundException::class);
        $this->expectExceptionMessage('Task is deleted');
        $handler->__invoke($query);
    }

    public function testThrowsTaskNotFoundWhenOutputIsMissing(): void
    {
        // COMPLETED, not deleted, but no 'output' in meta
        $task = Task::reconstitute(
            videoId: Uuid::fromString('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'),
            presetId: Uuid::fromString('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'),
            userId: Uuid::fromString('cccccccc-cccc-4ccc-8ccc-cccccccccccc'),
            status: TaskStatus::COMPLETED,
            progress: new Progress(100),
            dates: TaskDates::create(),
            id: Uuid::fromString('dddddddd-dddd-4ddd-8ddd-dddddddddddd'),
            meta: [],
        );

        $video = VideoFake::create();
        $query = new TaskDownloadQuery(
            $task->id()->toRfc4122(),
            Uuid::generate()->toRfc4122(),
        );

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn($task);

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn($video);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(true);

        $handler = new TaskDownloadHandler(
            $taskRepository,
            $videoRepository,
            $this->createStub(LogServiceInterface::class),
            $security,
            $this->createStub(StorageInterface::class),
        );

        $this->expectException(TaskNotFoundException::class);
        $this->expectExceptionMessage('Output file not found');
        $handler->__invoke($query);
    }

    public function testReturnsPublicUrlAndLogsOnSuccess(): void
    {
        $taskId = Uuid::fromString('dddddddd-dddd-4ddd-8ddd-dddddddddddd');
        $userId = Uuid::fromString('eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee');
        $outputKey = 'some/output.mp4';
        $publicUrl = 'https://cdn.example.com/some/output.mp4';

        $task = Task::reconstitute(
            videoId: Uuid::fromString('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'),
            presetId: Uuid::fromString('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'),
            userId: $userId,
            status: TaskStatus::COMPLETED,
            progress: new Progress(100),
            dates: TaskDates::create(),
            id: $taskId,
            meta: ['output' => $outputKey],
        );

        $video = VideoFake::create();
        $query = new TaskDownloadQuery($taskId->toRfc4122(), $userId->toRfc4122());

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn($task);

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn($video);

        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('isGranted')
            ->with(VideoAccessVoter::CAN_DOWNLOAD_TRANSCODE, $video)
            ->willReturn(true);

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())
            ->method('log')
            ->with(
                'task',
                'download',
                $taskId,
                LogLevel::INFO,
                'Transcode result downloaded',
                $this->callback(fn(array $ctx): bool =>
                    $ctx['output'] === $outputKey
                    && $ctx['userId'] === $userId->toRfc4122()
                    && $ctx['videoId'] === $video->id()?->toRfc4122()
                ),
            );

        $storage = $this->createMock(StorageInterface::class);
        $storage->expects($this->once())
            ->method('publicUrl')
            ->with($outputKey)
            ->willReturn($publicUrl);

        $handler = new TaskDownloadHandler(
            $taskRepository,
            $videoRepository,
            $logService,
            $security,
            $storage,
        );

        $result = $handler->__invoke($query);

        $this->assertSame($publicUrl, $result);
    }
}
