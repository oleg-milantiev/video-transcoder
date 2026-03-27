<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Task;

use App\Application\Service\Task\TaskRealtimeNotifier;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\ValueObject\PresetTitle;
use App\Domain\Video\ValueObject\Resolution;
use App\Domain\Video\ValueObject\Codec;
use App\Domain\Video\ValueObject\Bitrate;
use App\Domain\Video\ValueObject\VideoTitle;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Shared\ValueObject\Uuid;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class TaskRealtimeNotifierTest extends TestCase
{
    public function testDispatchesMercureMessageWhenTaskHasId(): void
    {
        $task = Task::create(Uuid::generate(), Uuid::generate(), Uuid::generate());
        $task->assignId(Uuid::generate());

        $video = Video::reconstitute(new VideoTitle('Title'), new FileExtension('mp4'), $task->userId(), [], VideoDates::create(), $task->videoId());
        $preset = new Preset(new PresetTitle('Preset'), new Resolution(1280, 720), new Codec('h264'), new Bitrate(3.0), id: $task->presetId());

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $presetRepo = $this->createStub(\App\Domain\Video\Repository\PresetRepositoryInterface::class);
        $presetRepo->method('findById')->willReturn($preset);

        $videoRepo = $this->createStub(\App\Domain\Video\Repository\VideoRepositoryInterface::class);
        $videoRepo->method('findById')->willReturn($video);

        $notifier = new TaskRealtimeNotifier($commandBus, $presetRepo, $videoRepo);

        $notifier->notifyTaskUpdated($task, 'updated', ['foo' => 'bar']);
    }

    public function testDoesNotDispatchWhenTaskHasNoId(): void
    {
        $task = Task::create(Uuid::generate(), Uuid::generate(), Uuid::generate());

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->never())->method('dispatch');

        $notifier = new TaskRealtimeNotifier($commandBus, $this->createStub(\App\Domain\Video\Repository\PresetRepositoryInterface::class), $this->createStub(\App\Domain\Video\Repository\VideoRepositoryInterface::class));

        $notifier->notifyTaskUpdated($task);
    }
}

