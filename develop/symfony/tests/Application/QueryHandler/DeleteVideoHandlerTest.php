<?php

declare(strict_types=1);

namespace App\Tests\Application\QueryHandler;

use App\Application\Command\Video\CleanupDeletedVideoMedia;
use App\Application\Event\DeleteVideoFail;
use App\Application\Event\DeleteVideoStart;
use App\Application\Event\DeleteVideoSuccess;
use App\Application\Exception\TranscodeAccessDeniedException;
use App\Application\Exception\VideoNotFoundException;
use App\Application\Logging\LogServiceInterface;
use App\Application\Query\DeleteTaskQuery;
use App\Application\Query\DeleteVideoQuery;
use App\Application\QueryHandler\DeleteVideoHandler;
use App\Application\QueryHandler\QueryBus;
use App\Application\Service\StorageRealtimeNotifierInterface;
use App\Application\Service\Video\VideoRealtimeNotifier;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Exception\VideoHasTranscodingTasks;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskDates;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoTitle;
use App\Infrastructure\Security\Voter\VideoAccessVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Domain\Shared\ValueObject\Uuid;

final class DeleteVideoHandlerTest extends TestCase
{
    public function testThrowsWhenVideoNotFound(): void
    {
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $userId = Uuid::fromString('22222222-2222-4222-8222-222222222222');

        $eventBus = new class implements MessageBusInterface {
            public array $events = [];

            public function dispatch($message, array $stamps = []): Envelope
            {
                $this->events[] = $message;
                return new Envelope($message);
            }
        };

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->once())
            ->method('findById')
            ->with($videoId)
            ->willReturn(null);

        $handler = new DeleteVideoHandler(
            $this->createStub(MessageBusInterface::class),
            $eventBus,
            $videoRepository,
            $this->createStub(TaskRepositoryInterface::class),
            $this->createStub(LogServiceInterface::class),
            new VideoRealtimeNotifier($this->createStub(MessageBusInterface::class), $this->createStub(StorageInterface::class), $this->createStub(TaskRepositoryInterface::class)),
            $this->createStub(Security::class),
            $this->createStub(QueryBus::class),
            $this->createStub(StorageRealtimeNotifierInterface::class),
        );

        $this->expectException(VideoNotFoundException::class);
        try {
            $handler(new DeleteVideoQuery($videoId->toRfc4122(), $userId->toRfc4122()));
        } finally {
            $this->assertSame([DeleteVideoStart::class, DeleteVideoFail::class], array_map(
                static fn (object $event): string => $event::class,
                $eventBus->events,
            ));
        }
    }

    public function testThrowsWhenAccessDenied(): void
    {
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $userId = Uuid::fromString('22222222-2222-4222-8222-222222222222');
        $video = $this->createVideo($videoId, $userId);

        $eventBus = new class implements MessageBusInterface {
            public array $events = [];

            public function dispatch($message, array $stamps = []): Envelope
            {
                $this->events[] = $message;
                return new Envelope($message);
            }
        };

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn($video);

        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('isGranted')
            ->with(VideoAccessVoter::CAN_DELETE, $video)
            ->willReturn(false);

        $handler = new DeleteVideoHandler(
            $this->createStub(MessageBusInterface::class),
            $eventBus,
            $videoRepository,
            $this->createStub(TaskRepositoryInterface::class),
            $this->createStub(LogServiceInterface::class),
            new VideoRealtimeNotifier($this->createStub(MessageBusInterface::class), $this->createStub(StorageInterface::class), $this->createStub(TaskRepositoryInterface::class)),
            $security,
            $this->createStub(QueryBus::class),
            $this->createStub(StorageRealtimeNotifierInterface::class),
        );

        $this->expectException(TranscodeAccessDeniedException::class);
        try {
            $handler(new DeleteVideoQuery($videoId->toRfc4122(), $userId->toRfc4122()));
        } finally {
            $this->assertSame([DeleteVideoStart::class, DeleteVideoFail::class], array_map(
                static fn (object $event): string => $event::class,
                $eventBus->events,
            ));
        }
    }

    public function testMarksVideoAndTasksAsDeleted(): void
    {
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $userId = Uuid::fromString('22222222-2222-4222-8222-222222222222');
        $video = $this->createVideo($videoId, $userId);

        $task = Task::reconstitute(
            videoId: $videoId,
            presetId: Uuid::fromString('33333333-3333-4333-8333-333333333333'),
            userId: $userId,
            status: TaskStatus::COMPLETED,
            progress: new Progress(100),
            dates: TaskDates::create(),
            id: Uuid::fromString('44444444-4444-4444-8444-444444444444'),
        );

        $eventBus = new class implements MessageBusInterface {
            public array $events = [];

            public function dispatch($message, array $stamps = []): Envelope
            {
                $this->events[] = $message;
                return new Envelope($message);
            }
        };

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->once())
            ->method('findById')
            ->with($videoId)
            ->willReturn($video);
        $videoRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(static fn (Video $value): bool => $value->isDeleted()));

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())
            ->method('findByVideoId')
            ->with($videoId)
            ->willReturn([$task]);

         $queryBus = $this->createMock(QueryBus::class);
         $queryBus->expects($this->once())
             ->method('query')
             ->with($this->callback(static function (object $query) use ($task, $userId): bool {
                 return $query instanceof DeleteTaskQuery
                     && $query->taskId->toRfc4122() === $task->id()?->toRfc4122()
                     && $query->requestedByUserId->toRfc4122() === $userId->toRfc4122();
             }));

         $logService = $this->createMock(LogServiceInterface::class);
         $logService->expects($this->exactly(1))->method('log');

        $cleanupCommandBus = $this->createMock(MessageBusInterface::class);
        $cleanupCommandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static fn (object $message): bool => $message instanceof CleanupDeletedVideoMedia && $message->videoId->toRfc4122() === $videoId->toRfc4122()))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $notifierCommandBus = $this->createMock(MessageBusInterface::class);
        $notifierCommandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $message): Envelope {
                return new Envelope($message);
            });
        $videoRealtimeNotifier = new VideoRealtimeNotifier($notifierCommandBus, $this->createStub(StorageInterface::class), $this->createStub(TaskRepositoryInterface::class));

        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('isGranted')
            ->with(VideoAccessVoter::CAN_DELETE, $video)
            ->willReturn(true);

        $handler = new DeleteVideoHandler($cleanupCommandBus, $eventBus, $videoRepository, $taskRepository, $logService, $videoRealtimeNotifier, $security, $queryBus, $this->createStub(StorageRealtimeNotifierInterface::class));
        $handler(new DeleteVideoQuery($videoId->toRfc4122(), $userId->toRfc4122()));

        $this->assertTrue($video->isDeleted());
        $this->assertContains(DeleteVideoSuccess::class, array_map(
            static fn (object $event): string => $event::class,
            $eventBus->events,
        ));
    }

    public function testThrowsWhenVideoHasActiveTask(): void
    {
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $userId = Uuid::fromString('22222222-2222-4222-8222-222222222222');
        $video = $this->createVideo($videoId, $userId);
        $activeTask = Task::create(
            $videoId,
            Uuid::fromString('33333333-3333-4333-8333-333333333333'),
            $userId,
        );

        $eventBus = new class implements MessageBusInterface {
            public array $events = [];

            public function dispatch($message, array $stamps = []): Envelope
            {
                $this->events[] = $message;
                return new Envelope($message);
            }
        };

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn($video);

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findByVideoId')->willReturn([$activeTask]);

        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('isGranted')
            ->with(VideoAccessVoter::CAN_DELETE, $video)
            ->willReturn(true);

        $handler = new DeleteVideoHandler(
            $this->createStub(MessageBusInterface::class),
            $eventBus,
            $videoRepository,
            $taskRepository,
            $this->createStub(LogServiceInterface::class),
            new VideoRealtimeNotifier($this->createStub(MessageBusInterface::class), $this->createStub(StorageInterface::class), $this->createStub(TaskRepositoryInterface::class)),
            $security,
            $this->createStub(QueryBus::class),
            $this->createStub(StorageRealtimeNotifierInterface::class),
        );

        $this->expectException(VideoHasTranscodingTasks::class);
        try {
            $handler(new DeleteVideoQuery($videoId->toRfc4122(), $userId->toRfc4122()));
        } finally {
            $this->assertSame([DeleteVideoStart::class, DeleteVideoFail::class], array_map(
                static fn (object $event): string => $event::class,
                $eventBus->events,
            ));
        }
    }

    public function testThrowsWhenDeletionFailsAndDispatchesFail(): void
    {
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $userId = Uuid::fromString('22222222-2222-4222-8222-222222222222');
        $video = $this->createVideo($videoId, $userId);

        $eventBus = new class implements MessageBusInterface {
            public array $events = [];

            public function dispatch($message, array $stamps = []): Envelope
            {
                $this->events[] = $message;
                return new Envelope($message);
            }
        };

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->once())
            ->method('findById')
            ->with($videoId)
            ->willReturn($video);
        $videoRepository->expects($this->once())
            ->method('save')
            ->willThrowException(new \RuntimeException('Save failed during deletion'));

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())
            ->method('findByVideoId')
            ->with($videoId)
            ->willReturn([]);

        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('isGranted')
            ->with(VideoAccessVoter::CAN_DELETE, $video)
            ->willReturn(true);

        $logService = $this->createStub(LogServiceInterface::class);

        $handler = new DeleteVideoHandler(
            $this->createStub(MessageBusInterface::class),
            $eventBus,
            $videoRepository,
            $taskRepository,
            $logService,
            new VideoRealtimeNotifier($this->createStub(MessageBusInterface::class), $this->createStub(StorageInterface::class), $this->createStub(TaskRepositoryInterface::class)),
            $security,
            $this->createStub(QueryBus::class),
            $this->createStub(StorageRealtimeNotifierInterface::class),
        );

        $this->expectException(\RuntimeException::class);
        try {
            $handler(new DeleteVideoQuery($videoId->toRfc4122(), $userId->toRfc4122()));
        } finally {
            $this->assertContains(DeleteVideoFail::class, array_map(
                static fn (object $event): string => $event::class,
                $eventBus->events,
            ));
        }
    }

    public function testDeletesMultipleTasksInSequence(): void
    {
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $userId = Uuid::fromString('22222222-2222-4222-8222-222222222222');
        $video = $this->createVideo($videoId, $userId);

        $task1 = Task::reconstitute(
            videoId: $videoId,
            presetId: Uuid::fromString('33333333-3333-4333-8333-333333333333'),
            userId: $userId,
            status: TaskStatus::COMPLETED,
            progress: new Progress(100),
            dates: TaskDates::create(),
            id: Uuid::fromString('44444444-4444-4444-8444-444444444444'),
        );

        $task2 = Task::reconstitute(
            videoId: $videoId,
            presetId: Uuid::fromString('55555555-5555-4555-8555-555555555555'),
            userId: $userId,
            status: TaskStatus::COMPLETED,
            progress: new Progress(100),
            dates: TaskDates::create(),
            id: Uuid::fromString('66666666-6666-4666-8666-666666666666'),
        );

        $eventBus = new class implements MessageBusInterface {
            public array $events = [];

            public function dispatch($message, array $stamps = []): Envelope
            {
                $this->events[] = $message;
                return new Envelope($message);
            }
        };

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->once())
            ->method('findById')
            ->with($videoId)
            ->willReturn($video);
        $videoRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(static fn (Video $value): bool => $value->isDeleted()));

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())
            ->method('findByVideoId')
            ->with($videoId)
            ->willReturn([$task1, $task2]);

         $queryBus = $this->createMock(QueryBus::class);
         $queryBus->expects($this->exactly(2))
             ->method('query')
             ->willReturnCallback(static function (object $query) {
                 return null;
             });

         $logService = $this->createMock(LogServiceInterface::class);
         $logService->expects($this->exactly(1))->method('log');

        $cleanupCommandBus = $this->createMock(MessageBusInterface::class);
        $cleanupCommandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $notifierCommandBus = $this->createMock(MessageBusInterface::class);
        $notifierCommandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $message): Envelope {
                return new Envelope($message);
            });
        $videoRealtimeNotifier = new VideoRealtimeNotifier($notifierCommandBus, $this->createStub(StorageInterface::class), $this->createStub(TaskRepositoryInterface::class));

        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('isGranted')
            ->with(VideoAccessVoter::CAN_DELETE, $video)
            ->willReturn(true);

        $handler = new DeleteVideoHandler($cleanupCommandBus, $eventBus, $videoRepository, $taskRepository, $logService, $videoRealtimeNotifier, $security, $queryBus, $this->createStub(StorageRealtimeNotifierInterface::class));
        $handler(new DeleteVideoQuery($videoId->toRfc4122(), $userId->toRfc4122()));

        $this->assertTrue($video->isDeleted());
        $this->assertContains(DeleteVideoSuccess::class, array_map(
            static fn (object $event): string => $event::class,
            $eventBus->events,
        ));
    }

    public function testSkipsAlreadyDeletedTasksInLoop(): void
    {
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $userId = Uuid::fromString('22222222-2222-4222-8222-222222222222');
        $video = $this->createVideo($videoId, $userId);

        // One task that is already deleted — must be skipped (continue)
        $deletedTask = Task::reconstitute(
            videoId: $videoId,
            presetId: Uuid::fromString('33333333-3333-4333-8333-333333333333'),
            userId: $userId,
            status: TaskStatus::DELETED,
            progress: new Progress(0),
            dates: TaskDates::create(),
            id: Uuid::fromString('44444444-4444-4444-8444-444444444411'),
            deleted: true,
        );

        // One non-deleted completed task — must be processed normally
        $completedTask = Task::reconstitute(
            videoId: $videoId,
            presetId: Uuid::fromString('55555555-5555-4555-8555-555555555555'),
            userId: $userId,
            status: TaskStatus::COMPLETED,
            progress: new Progress(100),
            dates: TaskDates::create(),
            id: Uuid::fromString('66666666-6666-4666-8666-666666666666'),
        );

        $events = [];
        $eventBus = new class ($events) implements MessageBusInterface {
            public function __construct(private array &$events) {}
            public function dispatch($message, array $stamps = []): Envelope
            {
                $this->events[] = $message::class;
                return new Envelope($message);
            }
        };

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->once())->method('findById')->willReturn($video);
        $videoRepository->expects($this->once())->method('save');

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findByVideoId')->willReturn([$deletedTask, $completedTask]);

        // queryBus must be called exactly ONCE — only for the non-deleted task
        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())->method('query');

        $security = $this->createMock(Security::class);
        $security->expects($this->once())->method('isGranted')->willReturn(true);

        $cleanupCommandBus = $this->createMock(MessageBusInterface::class);
        $cleanupCommandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static fn (object $m): Envelope => new Envelope($m));

        $notifierCommandBus = $this->createMock(MessageBusInterface::class);
        $notifierCommandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static fn (object $m): Envelope => new Envelope($m));

        $handler = new DeleteVideoHandler(
            $cleanupCommandBus,
            $eventBus,
            $videoRepository,
            $taskRepository,
            $this->createStub(LogServiceInterface::class),
            new VideoRealtimeNotifier($notifierCommandBus, $this->createStub(StorageInterface::class), $this->createStub(TaskRepositoryInterface::class)),
            $security,
            $queryBus,
            $this->createStub(StorageRealtimeNotifierInterface::class),
        );

        $handler(new DeleteVideoQuery($videoId->toRfc4122(), $userId->toRfc4122()));

        $this->assertContains(DeleteVideoSuccess::class, $events);
    }

    private function createVideo(Uuid $videoId, Uuid $userId): Video
    {
        return Video::reconstitute(
            new VideoTitle('Delete me'),
            new FileExtension('mp4'),
            $userId,
            [],
            VideoDates::create(),
            $videoId,
        );
    }
}
