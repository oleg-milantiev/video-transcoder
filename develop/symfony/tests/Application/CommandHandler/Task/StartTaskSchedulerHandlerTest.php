<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler\Task;

use App\Application\Command\Task\StartTaskScheduler;
use App\Application\Command\Task\TranscodeVideo;
use App\Application\CommandHandler\Task\StartTaskSchedulerHandler;
use App\Application\DTO\ScheduledTaskDTO;
use App\Application\Event\StartTaskSchedulerFail;
use App\Application\Event\StartTaskSchedulerStart;
use App\Application\Event\StartTaskSchedulerSuccess;
use App\Application\Query\Repository\ScheduledTaskReadRepositoryInterface;
use App\Infrastructure\Task\TaskCancellationTrigger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Domain\Shared\ValueObject\Uuid;

class StartTaskSchedulerHandlerTest extends TestCase
{
    public function testDispatchesLifecycleEventsOnSuccess(): void
    {
        $taskRepository = $this->createStub(ScheduledTaskReadRepositoryInterface::class);
        $taskRepository->method('getScheduled')->willReturn([
            new ScheduledTaskDTO(
                Uuid::fromString('123e4567-e89b-42d3-a456-426614174110'),
                Uuid::fromString('123e4567-e89b-42d3-a456-426614174107'),
                Uuid::fromString('123e4567-e89b-42d3-a456-426614174141')
            ),
        ]);

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(TranscodeVideo::class))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $eventBus = $this->createMock(MessageBusInterface::class);
        $events = [];
        $eventBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$events): Envelope {
                $events[] = $message::class;

                return new Envelope($message);
            });
        $taskCancellationTrigger = $this->createStub(TaskCancellationTrigger::class);
        $taskCancellationTrigger->method('clear');

        $handler = new StartTaskSchedulerHandler(
            $this->createStub(LoggerInterface::class),
            $taskRepository,
            $commandBus,
            $eventBus,
            $taskCancellationTrigger,
        );

        $handler(new StartTaskScheduler());

        $this->assertSame([
            StartTaskSchedulerStart::class,
            StartTaskSchedulerSuccess::class,
        ], $events);
    }

    public function testDispatchesFailedEventAndRethrows(): void
    {
        $taskRepository = $this->createStub(ScheduledTaskReadRepositoryInterface::class);
        $taskRepository->method('getScheduled')->willReturn([
            new ScheduledTaskDTO(
                Uuid::fromString('123e4567-e89b-42d3-a456-426614174111'),
                Uuid::fromString('123e4567-e89b-42d3-a456-426614174108'),
                Uuid::fromString('123e4567-e89b-42d3-a456-426614174142')
            ),
        ]);

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())
            ->method('dispatch');
        $commandBus->method('dispatch')->willThrowException(new \RuntimeException('dispatch failed'));

        $eventBus = $this->createMock(MessageBusInterface::class);
        $events = [];
        $eventBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$events): Envelope {
                $events[] = $message::class;

                return new Envelope($message);
            });

        $taskCancellationTrigger = $this->createStub(TaskCancellationTrigger::class);
        $taskCancellationTrigger->method('clear');

        $handler = new StartTaskSchedulerHandler(
            $this->createStub(LoggerInterface::class),
            $taskRepository,
            $commandBus,
            $eventBus,
            $taskCancellationTrigger,
        );

        $this->expectException(\RuntimeException::class);

        try {
            $handler(new StartTaskScheduler());
        } finally {
            $this->assertSame([
                StartTaskSchedulerStart::class,
                StartTaskSchedulerFail::class,
            ], $events);
        }
    }
}
