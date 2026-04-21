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
use App\Domain\Video\ValueObject\PresetTitle;
use App\Domain\Video\ValueObject\VideoCodec;
use App\Domain\Video\ValueObject\AudioCodec;
use App\Domain\Video\ValueObject\Format;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskDates;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\ValueObject\VideoTitle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class TaskRealtimeNotifierTest extends TestCase
{
    private function makeVideo(string $title, Uuid $videoId, Uuid $userId): Video
    {
        return Video::reconstitute(
            new VideoTitle($title),
            new FileExtension('mp4'),
            $userId,
            [],
            VideoDates::create(),
            $videoId,
        );
    }

    private function makePreset(string $title, Uuid $presetId): Preset
    {
        return new Preset(
            new PresetTitle($title),
            new VideoCodec('h264'),
            new AudioCodec('aac'),
            new Format('mp4'),
            id: $presetId,
        );
    }

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
            $videoId, $presetId, $userId,
            TaskStatus::processing(),
            new Progress(10),
            TaskDates::create(),
            $taskId, [], false,
        );

        $videoRepository->method('findById')->willReturn($this->makeVideo('My video', $videoId, $userId));
        $presetRepository->method('findById')->willReturn($this->makePreset('Test Preset', $presetId));

        $commandBus->expects($this->once())->method('dispatch')->willReturnCallback(function ($command, $stamps = []) use ($task) {
            if (!$command instanceof PublishMercureMessage) {
                throw new \RuntimeException('Expected PublishMercureMessage');
            }

            $message = $command->message;
            if (!$message instanceof MercureMessageDTO) {
                throw new \RuntimeException('Expected MercureMessageDTO');
            }

            TestCase::assertSame('updated', $message->action);
            TestCase::assertSame('task', $message->entity);
            TestCase::assertTrue($message->id->equals($task->id()));
            TestCase::assertTrue($message->userId->equals($task->userId()));
            TestCase::assertIsArray($message->payload);
            TestCase::assertSame($task->id()->toRfc4122(), $message->payload['taskId']);
            TestCase::assertSame('My video', $message->payload['videoTitle']);
            TestCase::assertSame('Test Preset', $message->payload['presetTitle']);
            TestCase::assertSame('h264', $message->payload['presetVideoCodec']);
            TestCase::assertSame('aac', $message->payload['presetAudioCodec']);
            TestCase::assertSame('mp4', $message->payload['presetFormat']);

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
            $videoId, $presetId, $userId,
            TaskStatus::pending(),
            new Progress(0),
            TaskDates::create(),
            $taskId, [], false,
        );

        $videoRepository->method('findById')->willReturn($this->makeVideo('Another video', $videoId, $userId));
        $presetRepository->method('findById')->willReturn($this->makePreset('Test Preset', $presetId));

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
            TestCase::assertSame($task->id()->toRfc4122(), $message->payload['taskId']);
            TestCase::assertSame('Another video', $message->payload['videoTitle']);
            TestCase::assertSame('Test Preset', $message->payload['presetTitle']);

            return new Envelope($command);
        });

        $notifier = new TaskRealtimeNotifier($commandBus, $presetRepository, $videoRepository);
        $notifier->notifyTaskUpdated($task, 'created');
    }

    public function testNotifyTaskDeletedFetchesVideoAndPresetAndSetsDeletedFlag(): void
    {
        $commandBus = $this->createMock(MessageBusInterface::class);
        $presetRepository = $this->createMock(PresetRepositoryInterface::class);
        $videoRepository = $this->createMock(VideoRepositoryInterface::class);

        $taskId = Uuid::generate();
        $videoId = Uuid::generate();
        $presetId = Uuid::generate();
        $userId = Uuid::generate();

        $task = Task::reconstitute(
            $videoId, $presetId, $userId,
            TaskStatus::deleted(),
            new Progress(0),
            TaskDates::create(),
            $taskId, [], true,
        );

        $videoRepository->expects($this->once())->method('findById')
            ->willReturn($this->makeVideo('Some video', $videoId, $userId));
        $presetRepository->expects($this->once())->method('findById')
            ->willReturn($this->makePreset('HD 720p', $presetId));

        $commandBus->expects($this->once())->method('dispatch')->willReturnCallback(function ($command, $stamps = []) {
            if (!$command instanceof PublishMercureMessage) {
                throw new \RuntimeException('Expected PublishMercureMessage');
            }

            $message = $command->message;
            TestCase::assertSame('deleted', $message->action);
            TestCase::assertSame('task', $message->entity);
            TestCase::assertIsArray($message->payload);
            TestCase::assertTrue($message->payload['deleted']);

            return new Envelope($command);
        });

        $notifier = new TaskRealtimeNotifier($commandBus, $presetRepository, $videoRepository);
        $notifier->notifyTaskUpdated($task, 'deleted');
    }

    public function testNotifySkippedWhenVideoOrPresetNotFound(): void
    {
        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->never())->method('dispatch');

        $presetRepository = $this->createStub(PresetRepositoryInterface::class);
        $videoRepository = $this->createStub(VideoRepositoryInterface::class);

        $videoRepository->method('findById')->willReturn(null);
        $presetRepository->method('findById')->willReturn(null);

        $task = Task::reconstitute(
            Uuid::generate(), Uuid::generate(), Uuid::generate(),
            TaskStatus::pending(),
            new Progress(0),
            TaskDates::create(),
            Uuid::generate(), [], false,
        );

        $notifier = new TaskRealtimeNotifier($commandBus, $presetRepository, $videoRepository);
        $notifier->notifyTaskUpdated($task);
    }

    public function testDoesNothingWhenTaskHasNoId(): void
    {
        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->never())->method('dispatch');

        $task = Task::create(Uuid::generate(), Uuid::generate(), Uuid::generate());

        $notifier = new TaskRealtimeNotifier(
            $commandBus,
            $this->createStub(PresetRepositoryInterface::class),
            $this->createStub(VideoRepositoryInterface::class),
        );

        $notifier->notifyTaskUpdated($task);
    }
}
