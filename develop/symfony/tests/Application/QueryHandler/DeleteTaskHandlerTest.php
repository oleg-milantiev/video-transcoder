<?php

declare(strict_types=1);

namespace App\Tests\Application\QueryHandler;

use App\Application\Query\DeleteTaskQuery;
use App\Application\QueryHandler\DeleteTaskHandler;
use App\Application\Exception\TaskNotFoundException;
use App\Application\Exception\TranscodeAccessDeniedException;
use App\Application\Logging\LogServiceInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Infrastructure\Security\Voter\VideoAccessVoter;
use App\Tests\Domain\Entity\TaskFake;
use App\Tests\Domain\Entity\VideoFake;
use App\Domain\Video\ValueObject\Progress;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use App\Domain\Shared\ValueObject\Uuid;

final class DeleteTaskHandlerTest extends TestCase
{
    public function testInvokeThrowsWhenTaskNotFound(): void
    {
        $taskId = '00000000-0000-4000-8000-000000000101';
        $query = new DeleteTaskQuery($taskId, '00000000-0000-4000-8000-000000000102');

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn(null);

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $logService = $this->createStub(LogServiceInterface::class);
        $security = $this->createStub(Security::class);

        $handler = new DeleteTaskHandler($taskRepository, $videoRepository, $logService, $security);

        $this->expectException(TaskNotFoundException::class);
        $handler->__invoke($query);
    }

    public function testInvokeThrowsWhenVideoNotFound(): void
    {
        $task = TaskFake::create();
        $taskIdStr = $task->id()?->toRfc4122() ?? Uuid::generate()->toRfc4122();
        $query = new DeleteTaskQuery($taskIdStr, '00000000-0000-4000-8000-000000000102');

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn($task);

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn(null);

        $logService = $this->createStub(LogServiceInterface::class);
        $security = $this->createStub(Security::class);

        $handler = new DeleteTaskHandler($taskRepository, $videoRepository, $logService, $security);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Task video not found');
        $handler->__invoke($query);
    }

    public function testInvokeThrowsWhenAccessDenied(): void
    {
        $task = TaskFake::create();
        $video = VideoFake::create();
        $taskIdStr = $task->id()?->toRfc4122() ?? Uuid::generate()->toRfc4122();
        $query = new DeleteTaskQuery($taskIdStr, '00000000-0000-4000-8000-000000000102');

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn($task);

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn($video);

        $logService = $this->createStub(LogServiceInterface::class);

        $security = $this->createMock(Security::class);
        $security->expects($this->once())->method('isGranted')->with(VideoAccessVoter::CAN_DELETE, $video)->willReturn(false);

        $handler = new DeleteTaskHandler($taskRepository, $videoRepository, $logService, $security);

        $this->expectException(TranscodeAccessDeniedException::class);
        $handler->__invoke($query);
    }

    public function testInvokeThrowsWhenTaskIsActive(): void
    {
        $task = TaskFake::create();
        $video = VideoFake::create();
        // set video duration and start the task to mark it processing
        $video->updateMeta(['duration' => 5.0]);
        $task->start($video->duration());

        $taskIdStr = $task->id()?->toRfc4122() ?? Uuid::generate()->toRfc4122();
        $query = new DeleteTaskQuery($taskIdStr, '00000000-0000-4000-8000-000000000102');

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn($task);

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn($video);

        $logService = $this->createStub(LogServiceInterface::class);

        $security = $this->createMock(Security::class);
        $security->expects($this->once())->method('isGranted')->with(VideoAccessVoter::CAN_DELETE, $video)->willReturn(true);

        $handler = new DeleteTaskHandler($taskRepository, $videoRepository, $logService, $security);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Task is active and cannot be deleted.');
        $handler->__invoke($query);
    }

    public function testInvokeMarksDeletedAndLogs(): void
    {
        $task = TaskFake::create();
        $video = VideoFake::create();

        // Ensure the task is finished and not in a transcoding state
        $video->updateMeta(['duration' => 1.0]);
        $task->start($video->duration());
        $task->updateProgress(new Progress(100));

        $taskIdStr = $task->id()?->toRfc4122() ?? Uuid::generate()->toRfc4122();
        $requestedBy = '00000000-0000-4000-8000-000000000102';
        $query = new DeleteTaskQuery($taskIdStr, $requestedBy);

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())
            ->method('findById')
            ->with($this->callback(fn($id) => $id->toRfc4122() === $task->id()?->toRfc4122()))
            ->willReturn($task);

        $taskRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function ($savedTask): bool {
                // saved task should be marked deleted
                return $savedTask->isDeleted();
            }));

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn($video);

        $calls = [];
        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->exactly(3))
            ->method('log')
            ->willReturnCallback(function ($entity, $id, $level, $message, $context) use (&$calls) {
                $calls[] = [$entity, $id, $level, $message, $context];
            });

        $security = $this->createMock(Security::class);
        $security->expects($this->once())->method('isGranted')->with(VideoAccessVoter::CAN_DELETE, $video)->willReturn(true);

        $handler = new DeleteTaskHandler($taskRepository, $videoRepository, $logService, $security);

        $handler->__invoke($query);

        // Assert log calls were made in expected order with expected messages
        $this->assertCount(3, $calls);
        $this->assertSame('task', $calls[0][0]);
        $this->assertSame('Task marked as deleted', $calls[0][3]);
        $this->assertSame('video', $calls[1][0]);
        $this->assertSame('Task deleted for video', $calls[1][3]);
        $this->assertSame('user', $calls[2][0]);
        $this->assertSame('User deleted task', $calls[2][3]);
    }
}
