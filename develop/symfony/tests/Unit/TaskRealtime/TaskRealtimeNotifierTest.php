<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaskRealtime;

use App\Application\Service\Task\TaskRealtimeNotifier;
use App\Application\Command\Mercure\PublishMercureMessage;
use App\Application\DTO\MercureMessageDTO;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\ValueObject\Bitrate;
use App\Domain\Video\ValueObject\Codec;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\PresetTitle;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\Resolution;
use App\Domain\Video\ValueObject\TaskDates;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\ValueObject\VideoTitle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class TaskRealtimeNotifierTest extends TestCase
{
    public function testNotifyTaskUpdatedDispatchesPublishCommandWithPayload(): void
    {
        $commandBus = $this->createMock(MessageBusInterface::class);
        $presetRepository = $this->createStub(PresetRepositoryInterface::class);
        $videoRepository = $this->createStub(VideoRepositoryInterface::class);

        $taskId = Uuid::generate();
        $videoId = Uuid::generate();
        $presetId = Uuid::generate();
        $userId = Uuid::generate();

        $task = Task::reconstitute(
            $videoId,
            $presetId,
            $userId,
            TaskStatus::processing(),
            new Progress(10),
            TaskDates::create(),
            $taskId,
            [],
            false,
        );

        $preset = Preset::create(
            new PresetTitle('HD1'),
            new Resolution(1920, 1080),
            new Codec('h264'),
            new Bitrate(4.0),
        );

        $video = Video::create(
            new VideoTitle('My video'),
            new FileExtension('mp4'),
            Uuid::generate(),
            [],
        );

        $videoRepository->method('findById')->willReturn($video);
        $presetRepository->method('findById')->willReturn($preset);

        $commandBus->expects($this->once())->method('dispatch')->willReturnCallback(function ($command, $stamps = []) use ($task) {
            // assertions about the dispatched command
            if (!$command instanceof PublishMercureMessage) {
                throw new \RuntimeException('Expected PublishMercureMessage');
            }

            $message = $command->message;
            if (!$message instanceof MercureMessageDTO) {
                throw new \RuntimeException('Expected MercureMessageDTO');
            }

            // basic checks
            TestCase::assertSame('updated', $message->action);
            TestCase::assertSame('task', $message->entity);
            TestCase::assertTrue($message->id->equals($task->id()));
            TestCase::assertTrue($message->userId->equals($task->userId()));
            TestCase::assertIsArray($message->payload);
            TestCase::assertSame('My video', $message->payload['videoTitle']);
            TestCase::assertSame('HD1', $message->payload['presetTitle']);

            return new Envelope($command);
        });

        $notifier = new TaskRealtimeNotifier($commandBus, $presetRepository, $videoRepository);

        $notifier->notifyTaskUpdated($task, 'updated');
    }

    public function testNotifyTaskCreatedIncludesVideoPresetFieldsAndActionCreated(): void
    {
        $commandBus = $this->createMock(MessageBusInterface::class);
        $presetRepository = $this->createStub(PresetRepositoryInterface::class);
        $videoRepository = $this->createStub(VideoRepositoryInterface::class);

        $taskId = Uuid::generate();
        $videoId = Uuid::generate();
        $presetId = Uuid::generate();
        $userId = Uuid::generate();

        $task = Task::reconstitute(
            $videoId,
            $presetId,
            $userId,
            TaskStatus::pending(),
            new Progress(0),
            TaskDates::create(),
            $taskId,
            [],
            false,
        );

        $preset = Preset::create(
            new PresetTitle('SD1'),
            new Resolution(720, 576),
            new Codec('h264'),
            new Bitrate(2.0),
        );

        $video = Video::create(
            new VideoTitle('Another video'),
            new FileExtension('mp4'),
            Uuid::generate(),
            [],
        );

        $videoRepository->method('findById')->willReturn($video);
        $presetRepository->method('findById')->willReturn($preset);

        $commandBus->expects($this->once())->method('dispatch')->willReturnCallback(function ($command, $stamps = []) use ($task) {
            if (!$command instanceof PublishMercureMessage) {
                throw new \RuntimeException('Expected PublishMercureMessage');
            }

            $message = $command->message;
            if (!$message instanceof MercureMessageDTO) {
                throw new \RuntimeException('Expected MercureMessageDTO');
            }

            TestCase::assertSame('created', $message->action);
            TestCase::assertSame('task', $message->entity);
            TestCase::assertTrue($message->id->equals($task->id()));
            TestCase::assertIsArray($message->payload);
            TestCase::assertSame('Another video', $message->payload['videoTitle']);
            TestCase::assertSame('SD1', $message->payload['presetTitle']);

            return new Envelope($command);
        });

        $notifier = new TaskRealtimeNotifier($commandBus, $presetRepository, $videoRepository);

        $notifier->notifyTaskUpdated($task, 'created');
    }

    public function testNotifyTaskDeletedDoesNotFetchVideoOrPreset(): void
    {
        $commandBus = $this->createMock(MessageBusInterface::class);
        $presetRepository = $this->createMock(PresetRepositoryInterface::class);
        $videoRepository = $this->createMock(VideoRepositoryInterface::class);

        $taskId = Uuid::generate();
        $videoId = Uuid::generate();
        $presetId = Uuid::generate();
        $userId = Uuid::generate();

        $task = Task::reconstitute(
            $videoId,
            $presetId,
            $userId,
            TaskStatus::deleted(),
            new Progress(0),
            TaskDates::create(),
            $taskId,
            [],
            true,
        );

        // Expect that repositories are NOT called
        $videoRepository->expects($this->never())->method('findById');
        $presetRepository->expects($this->never())->method('findById');

        $commandBus->expects($this->once())->method('dispatch')->willReturnCallback(function ($command, $stamps = []) use ($task) {
            if (!$command instanceof PublishMercureMessage) {
                throw new \RuntimeException('Expected PublishMercureMessage');
            }

            $message = $command->message;
            if (!$message instanceof MercureMessageDTO) {
                throw new \RuntimeException('Expected MercureMessageDTO');
            }

            TestCase::assertSame('deleted', $message->action);
            TestCase::assertSame('task', $message->entity);
            TestCase::assertTrue($message->id->equals($task->id()));
            TestCase::assertIsArray($message->payload);
            TestCase::assertTrue($message->payload['deleted']);

            return new Envelope($command);
        });

        $notifier = new TaskRealtimeNotifier($commandBus, $presetRepository, $videoRepository);

        $notifier->notifyTaskUpdated($task, 'deleted');
    }
}
