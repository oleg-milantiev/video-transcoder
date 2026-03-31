<?php

declare(strict_types=1);

namespace App\Tests\Application\QueryHandler;

use App\Application\Event\PatchVideoFail;
use App\Application\Exception\VideoNotFoundException;
use App\Application\Logging\LogServiceInterface;
use App\Application\Query\PatchVideoQuery;
use App\Application\QueryHandler\PatchVideoHandler;
use App\Application\Service\Video\VideoRealtimeNotifier;
use App\Application\Service\Task\TaskRealtimeNotifier;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskDates;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoTitle;
use App\Domain\Shared\ValueObject\Uuid;
use App\Infrastructure\Security\Voter\VideoAccessVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Domain\Video\Service\Storage\StorageInterface;

final class PatchVideoHandlerTest extends TestCase
{
    private function getRequestWithTitle(string $title): Request
    {
        $payload = json_encode(['title' => $title]);

        $request = $this->createStub(Request::class);
        $request->method('getContent')
            ->willReturn($payload);

        return $request;
    }

    public function testThrowsWhenVideoNotFound(): void
    {
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $userId = Uuid::fromString('22222222-2222-4222-8222-222222222222');

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->once())->method('findById')->with($videoId)->willReturn(null);

        $eventBus = $this->createStub(MessageBusInterface::class);
        $eventBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRealtimeNotifier = new TaskRealtimeNotifier($this->createStub(MessageBusInterface::class), $this->createStub(PresetRepositoryInterface::class), $this->createStub(VideoRepositoryInterface::class));

        $storageService = $this->createStub(StorageInterface::class);

        $handler = new PatchVideoHandler($eventBus, $videoRepository, $this->createStub(Security::class), new VideoRealtimeNotifier($this->createStub(MessageBusInterface::class), $this->createStub(StorageInterface::class), $this->createStub(TaskRepositoryInterface::class)), $taskRepository, $taskRealtimeNotifier, $this->createStub(LogServiceInterface::class), $storageService);

        $this->expectException(VideoNotFoundException::class);
        $handler(new PatchVideoQuery($videoId->toRfc4122(), $this->getRequestWithTitle('New Title'), $userId->toRfc4122()));
    }

    public function testThrowsWhenAccessDenied(): void
    {
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $userId = Uuid::fromString('22222222-2222-4222-8222-222222222222');
        $video = Video::reconstitute(new VideoTitle('Old'), new FileExtension('mp4'), $userId, [], VideoDates::create(), $videoId);

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn($video);

        $security = $this->createMock(Security::class);
        $security->expects($this->once())->method('isGranted')->with(VideoAccessVoter::CAN_EDIT, $video)->willReturn(false);

        $eventBus = $this->createStub(MessageBusInterface::class);
        $eventBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRealtimeNotifier = new TaskRealtimeNotifier($this->createStub(MessageBusInterface::class), $this->createStub(PresetRepositoryInterface::class), $this->createStub(VideoRepositoryInterface::class));

        $storageService = $this->createStub(StorageInterface::class);
        $handler = new PatchVideoHandler($eventBus, $videoRepository, $security, new VideoRealtimeNotifier($this->createStub(MessageBusInterface::class), $this->createStub(StorageInterface::class), $this->createStub(TaskRepositoryInterface::class)), $taskRepository, $taskRealtimeNotifier, $this->createStub(LogServiceInterface::class), $storageService);

        $this->expectException(\DomainException::class);
        $handler(new PatchVideoQuery($videoId->toRfc4122(), $this->getRequestWithTitle('New Title'), $userId->toRfc4122()));
    }

    public function testChangesTitleAndSaves(): void
    {
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $userId = Uuid::fromString('22222222-2222-4222-8222-222222222222');
        $video = Video::reconstitute(new VideoTitle('Old title'), new FileExtension('mp4'), $userId, [], VideoDates::create(), $videoId);

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->once())->method('findById')->with($videoId)->willReturn($video);
        $videoRepository->expects($this->once())->method('save')->with($this->callback(static function (Video $v): bool {
            return $v->id()->equals(Uuid::fromString('11111111-1111-4111-8111-111111111111'))
                && $v->title()->value() === 'New Title';
        }))->willReturnCallback(static fn (Video $v) => $v);

        $security = $this->createMock(Security::class);
        $security->expects($this->once())->method('isGranted')->with(VideoAccessVoter::CAN_EDIT, $video)->willReturn(true);

        $notifierBus = $this->createMock(MessageBusInterface::class);
        $notifierBus->expects($this->once())->method('dispatch')->willReturnCallback(static function ($m) { return new Envelope($m); });

        $videoRealtimeNotifier = new VideoRealtimeNotifier($notifierBus, $this->createStub(StorageInterface::class), $this->createStub(TaskRepositoryInterface::class));

        $eventBus = $this->createStub(MessageBusInterface::class);
        $eventBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRealtimeNotifier = new TaskRealtimeNotifier($notifierBus, $this->createStub(PresetRepositoryInterface::class), $this->createStub(VideoRepositoryInterface::class));

        $storageService = $this->createStub(StorageInterface::class);
        $handler = new PatchVideoHandler($eventBus, $videoRepository, $security, $videoRealtimeNotifier, $taskRepository, $taskRealtimeNotifier, $this->createStub(LogServiceInterface::class), $storageService);

        $handler(new PatchVideoQuery($videoId->toRfc4122(), $this->getRequestWithTitle('New Title'), $userId->toRfc4122()));

        $this->assertSame('New Title', $video->title()->value());
    }

    public function testUpdatesTasksWhenTitleChanges(): void
    {
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $userId = Uuid::fromString('22222222-2222-4222-8222-222222222222');
        $taskId = Uuid::fromString('33333333-3333-4333-8333-333333333333');
        $presetId = Uuid::fromString('44444444-4444-4444-8444-444444444444');

        $video = Video::reconstitute(new VideoTitle('Old title'), new FileExtension('mp4'), $userId, [], VideoDates::create(), $videoId);
        $task = Task::reconstitute(
            videoId: $videoId,
            presetId: $presetId,
            userId: $userId,
            status: TaskStatus::COMPLETED,
            progress: new Progress(100),
            dates: TaskDates::create(),
            id: $taskId,
        );

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->once())->method('findById')->with($videoId)->willReturn($video);
        $videoRepository->expects($this->once())->method('save')->with($this->callback(static function (Video $v): bool {
            return $v->id()->equals(Uuid::fromString('11111111-1111-4111-8111-111111111111'))
                && $v->title()->value() === 'New Title';
        }))->willReturnCallback(static fn (Video $v) => $v);

        $security = $this->createMock(Security::class);
        $security->expects($this->once())->method('isGranted')->with(VideoAccessVoter::CAN_EDIT, $video)->willReturn(true);

        $notifierBus = $this->createMock(MessageBusInterface::class);
        $notifierBus->expects($this->exactly(2))->method('dispatch')->willReturnCallback(static function ($m) { return new Envelope($m); });

        $videoRealtimeNotifier = new VideoRealtimeNotifier($notifierBus, $this->createStub(StorageInterface::class), $this->createStub(TaskRepositoryInterface::class));

        $eventBus = $this->createStub(MessageBusInterface::class);
        $eventBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->exactly(2))->method('findByVideoId')->with($videoId)->willReturn([$task]);

        $taskRealtimeNotifier = new TaskRealtimeNotifier($notifierBus, $this->createStub(PresetRepositoryInterface::class), $this->createStub(VideoRepositoryInterface::class));

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())->method('log');

        $storageService = $this->createStub(StorageInterface::class);
        $handler = new PatchVideoHandler($eventBus, $videoRepository, $security, $videoRealtimeNotifier, $taskRepository, $taskRealtimeNotifier, $logService, $storageService);

        $handler(new PatchVideoQuery($videoId->toRfc4122(), $this->getRequestWithTitle('New Title'), $userId->toRfc4122()));

        $this->assertSame('New Title', $video->title()->value());
    }

    public function testThrowsWhenSaveFailsAndDispatchesFail(): void
    {
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $userId = Uuid::fromString('22222222-2222-4222-8222-222222222222');
        $video = Video::reconstitute(new VideoTitle('Old title'), new FileExtension('mp4'), $userId, [], VideoDates::create(), $videoId);

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->once())->method('findById')->with($videoId)->willReturn($video);
        $videoRepository->expects($this->once())->method('save')->willThrowException(new \RuntimeException('Save failed'));

        $security = $this->createMock(Security::class);
        $security->expects($this->once())->method('isGranted')->with(VideoAccessVoter::CAN_EDIT, $video)->willReturn(true);

        $eventBus = $this->createMock(MessageBusInterface::class);
        $eventBus->expects($this->exactly(2))->method('dispatch')->willReturnCallback(
            static function ($message) {
                if (!$message instanceof PatchVideoFail) {
                    return new Envelope($message);
                }
                return new Envelope($message);
            }
        );

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRealtimeNotifier = new TaskRealtimeNotifier($this->createStub(MessageBusInterface::class), $this->createStub(PresetRepositoryInterface::class), $this->createStub(VideoRepositoryInterface::class));

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())->method('log');

        $storageService = $this->createStub(StorageInterface::class);

        $handler = new PatchVideoHandler(
            $eventBus,
            $videoRepository,
            $security,
            new VideoRealtimeNotifier($this->createStub(MessageBusInterface::class), $this->createStub(StorageInterface::class), $this->createStub(TaskRepositoryInterface::class)),
            $taskRepository,
            $taskRealtimeNotifier,
            $logService,
            $storageService,
        );

        $this->expectException(\RuntimeException::class);
        $handler(new PatchVideoQuery($videoId->toRfc4122(), $this->getRequestWithTitle('New Title'), $userId->toRfc4122()));
    }
}
