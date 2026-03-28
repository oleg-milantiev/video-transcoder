<?php

declare(strict_types=1);

namespace App\Tests\Application\QueryHandler;

use App\Application\Logging\LogServiceInterface;
use App\Application\Query\PatchVideoQuery;
use App\Application\QueryHandler\PatchVideoHandler;
use App\Application\Service\Video\VideoRealtimeNotifier;
use App\Application\Service\Task\TaskRealtimeNotifier;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface as VideoRepoInterface;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\TaskDates;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoTitle;
use App\Domain\Shared\ValueObject\Uuid;
use App\Infrastructure\Security\Voter\VideoAccessVoter;
use App\Tests\Domain\Entity\TaskFake;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Domain\Video\Service\Storage\StorageInterface;

final class PatchVideoHandlerTest extends TestCase
{
    public function testThrowsWhenVideoNotFound(): void
    {
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $userId = Uuid::fromString('22222222-2222-4222-8222-222222222222');

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->once())->method('findById')->with($videoId)->willReturn(null);

        $eventBus = $this->createStub(MessageBusInterface::class);
        $eventBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRealtimeNotifier = new TaskRealtimeNotifier($this->createStub(MessageBusInterface::class), $this->createStub(PresetRepositoryInterface::class), $this->createStub(VideoRepoInterface::class));

        $handler = new PatchVideoHandler($eventBus, $videoRepository, $this->createStub(Security::class), new VideoRealtimeNotifier($this->createStub(MessageBusInterface::class), $this->createStub(StorageInterface::class), $this->createStub(TaskRepositoryInterface::class)), $taskRepository, $taskRealtimeNotifier, $this->createStub(LogServiceInterface::class));

        $this->expectException(\App\Application\Exception\VideoNotFoundException::class);
        $handler(new PatchVideoQuery($videoId->toRfc4122(), 'New title', $userId->toRfc4122()));
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
        $taskRealtimeNotifier = new TaskRealtimeNotifier($this->createStub(MessageBusInterface::class), $this->createStub(PresetRepositoryInterface::class), $this->createStub(VideoRepoInterface::class));

        $handler = new PatchVideoHandler($eventBus, $videoRepository, $security, new VideoRealtimeNotifier($this->createStub(MessageBusInterface::class), $this->createStub(StorageInterface::class), $this->createStub(TaskRepositoryInterface::class)), $taskRepository, $taskRealtimeNotifier, $this->createStub(LogServiceInterface::class));

        $this->expectException(\DomainException::class);
        $handler(new PatchVideoQuery($videoId->toRfc4122(), 'New title', $userId->toRfc4122()));
    }

    public function testChangesTitleAndSaves(): void
    {
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $userId = Uuid::fromString('22222222-2222-4222-8222-222222222222');
        $video = Video::reconstitute(new VideoTitle('Old title'), new FileExtension('mp4'), $userId, [], VideoDates::create(), $videoId);

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->once())->method('findById')->with($videoId)->willReturn($video);
        $videoRepository->expects($this->once())->method('save')->with($this->callback(static function (Video $v): bool {
            return $v->title()->value() === 'New title';
        }))->willReturnCallback(static fn (Video $v) => $v);

        $security = $this->createMock(Security::class);
        $security->expects($this->once())->method('isGranted')->with(VideoAccessVoter::CAN_EDIT, $video)->willReturn(true);

        $notifierBus = $this->createMock(MessageBusInterface::class);
        $notifierBus->expects($this->once())->method('dispatch')->willReturnCallback(static function ($m) { return new \Symfony\Component\Messenger\Envelope($m); });

        $videoRealtimeNotifier = new VideoRealtimeNotifier($notifierBus, $this->createStub(StorageInterface::class), $this->createStub(TaskRepositoryInterface::class));

        $eventBus = $this->createStub(MessageBusInterface::class);
        $eventBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRealtimeNotifier = new TaskRealtimeNotifier($notifierBus, $this->createStub(PresetRepositoryInterface::class), $this->createStub(VideoRepoInterface::class));

        $handler = new PatchVideoHandler($eventBus, $videoRepository, $security, $videoRealtimeNotifier, $taskRepository, $taskRealtimeNotifier, $this->createStub(LogServiceInterface::class));

        $handler(new PatchVideoQuery($videoId->toRfc4122(), 'New title', $userId->toRfc4122()));

        $this->assertSame('New title', $video->title()->value());
    }
}
