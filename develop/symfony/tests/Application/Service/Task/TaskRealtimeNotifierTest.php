<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Task;

use App\Application\Service\Task\TaskRealtimeNotifier;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\ValueObject\Bitrate;
use App\Domain\Video\ValueObject\Codec;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\PresetTitle;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\Resolution;
use App\Domain\Video\ValueObject\TaskDates;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoTitle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class TaskRealtimeNotifierTest extends TestCase
{
    public function testNotifyTaskUpdatedDispatchesMessage(): void
    {
        $taskId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $videoId = Uuid::fromString('22222222-2222-4222-8222-222222222222');
        $presetId = Uuid::fromString('33333333-3333-4333-8333-333333333333');
        $userId = Uuid::fromString('44444444-4444-4444-8444-444444444444');

        $task = Task::reconstitute(
            videoId: $videoId,
            presetId: $presetId,
            userId: $userId,
            status: TaskStatus::PROCESSING,
            progress: new Progress(75),
            dates: TaskDates::create(),
            id: $taskId,
        );

        $video = Video::reconstitute(
            new VideoTitle('Test Video'),
            new FileExtension('mp4'),
            $userId,
            [],
            VideoDates::create(),
            $videoId,
        );

        $preset = new Preset(
            new PresetTitle('HD 720p'),
            new Resolution(1280, 720),
            new Codec('h264'),
            new Bitrate(50.0),
            id: $presetId,
        );

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $message): Envelope {
                return new Envelope($message);
            });

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->once())
            ->method('findById')
            ->with($videoId)
            ->willReturn($video);

        $presetRepository = $this->createMock(PresetRepositoryInterface::class);
        $presetRepository->expects($this->once())
            ->method('findById')
            ->with($presetId)
            ->willReturn($preset);

        $notifier = new TaskRealtimeNotifier($commandBus, $presetRepository, $videoRepository);
        $notifier->notifyTaskUpdated($task, 'updated', ['extra' => 'data']);
    }

    public function testNotifyTaskUpdatedDoesNothingWhenTaskHasNoId(): void
    {
        $task = Task::create(
            Uuid::fromString('22222222-2222-4222-8222-222222222222'),
            Uuid::fromString('33333333-3333-4333-8333-333333333333'),
            Uuid::fromString('44444444-4444-4444-8444-444444444444'),
        );

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->never())->method('dispatch');

        $notifier = new TaskRealtimeNotifier(
            $commandBus,
            $this->createStub(PresetRepositoryInterface::class),
            $this->createStub(VideoRepositoryInterface::class),
        );

        $notifier->notifyTaskUpdated($task);
    }

    public function testNotifyTaskUpdatedWithDeletedTask(): void
    {
        $taskId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $videoId = Uuid::fromString('22222222-2222-4222-8222-222222222222');
        $presetId = Uuid::fromString('33333333-3333-4333-8333-333333333333');
        $userId = Uuid::fromString('44444444-4444-4444-8444-444444444444');

        $task = Task::reconstitute(
            videoId: $videoId,
            presetId: $presetId,
            userId: $userId,
            status: TaskStatus::DELETED,
            progress: new Progress(0),
            dates: TaskDates::create(),
            id: $taskId,
            deleted: true,
        );

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $message): Envelope {
                return new Envelope($message);
            });

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn(null);

        $presetRepository = $this->createStub(PresetRepositoryInterface::class);
        $presetRepository->method('findById')->willReturn(null);

        $notifier = new TaskRealtimeNotifier($commandBus, $presetRepository, $videoRepository);
        $notifier->notifyTaskUpdated($task, 'deleted');
    }

    public function testNotifyTaskUpdatedWhenVideoNotFound(): void
    {
        $taskId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $videoId = Uuid::fromString('22222222-2222-4222-8222-222222222222');
        $presetId = Uuid::fromString('33333333-3333-4333-8333-333333333333');
        $userId = Uuid::fromString('44444444-4444-4444-8444-444444444444');

        $task = Task::reconstitute(
            videoId: $videoId,
            presetId: $presetId,
            userId: $userId,
            status: TaskStatus::PROCESSING,
            progress: new Progress(50),
            dates: TaskDates::create(),
            id: $taskId,
        );

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $message): Envelope {
                return new Envelope($message);
            });

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn(null);

        $presetRepository = $this->createStub(PresetRepositoryInterface::class);

        $notifier = new TaskRealtimeNotifier($commandBus, $presetRepository, $videoRepository);
        $notifier->notifyTaskUpdated($task);
    }
}
