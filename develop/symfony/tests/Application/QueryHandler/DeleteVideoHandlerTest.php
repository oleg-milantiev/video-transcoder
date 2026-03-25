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
use Symfony\Component\Uid\UuidV4;

final class DeleteVideoHandlerTest extends TestCase
{
    public function testThrowsWhenVideoNotFound(): void
    {
        $videoId = UuidV4::fromString('11111111-1111-4111-8111-111111111111');
        $userId = UuidV4::fromString('22222222-2222-4222-8222-222222222222');

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
            new VideoRealtimeNotifier($this->createStub(MessageBusInterface::class), $this->createStub(StorageInterface::class)),
            $this->createStub(Security::class),
            $this->createStub(QueryBus::class),
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
        $videoId = UuidV4::fromString('11111111-1111-4111-8111-111111111111');
        $userId = UuidV4::fromString('22222222-2222-4222-8222-222222222222');
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
            new VideoRealtimeNotifier($this->createStub(MessageBusInterface::class), $this->createStub(StorageInterface::class)),
            $security,
            $this->createStub(QueryBus::class),
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
        $videoId = UuidV4::fromString('11111111-1111-4111-8111-111111111111');
        $userId = UuidV4::fromString('22222222-2222-4222-8222-222222222222');
        $video = $this->createVideo($videoId, $userId);

        $task = Task::reconstitute(
            videoId: $videoId,
            presetId: UuidV4::fromString('33333333-3333-4333-8333-333333333333'),
            userId: $userId,
            status: TaskStatus::COMPLETED,
            progress: new Progress(100),
            dates: TaskDates::create(),
            id: UuidV4::fromString('44444444-4444-4444-8444-444444444444'),
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
        $logService->expects($this->exactly(2))->method('log');

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
        $videoRealtimeNotifier = new VideoRealtimeNotifier($notifierCommandBus, $this->createStub(StorageInterface::class));

        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('isGranted')
            ->with(VideoAccessVoter::CAN_DELETE, $video)
            ->willReturn(true);

        $handler = new DeleteVideoHandler($cleanupCommandBus, $eventBus, $videoRepository, $taskRepository, $logService, $videoRealtimeNotifier, $security, $queryBus);
        $handler(new DeleteVideoQuery($videoId->toRfc4122(), $userId->toRfc4122()));

        $this->assertTrue($video->isDeleted());
        $this->assertContains(DeleteVideoSuccess::class, array_map(
            static fn (object $event): string => $event::class,
            $eventBus->events,
        ));
    }

    public function testThrowsWhenVideoHasActiveTask(): void
    {
        $videoId = UuidV4::fromString('11111111-1111-4111-8111-111111111111');
        $userId = UuidV4::fromString('22222222-2222-4222-8222-222222222222');
        $video = $this->createVideo($videoId, $userId);
        $activeTask = Task::create(
            $videoId,
            UuidV4::fromString('33333333-3333-4333-8333-333333333333'),
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
            new VideoRealtimeNotifier($this->createStub(MessageBusInterface::class), $this->createStub(StorageInterface::class)),
            $security,
            $this->createStub(QueryBus::class),
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

    private function createVideo(UuidV4 $videoId, UuidV4 $userId): Video
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
