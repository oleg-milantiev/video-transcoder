<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Task;

use App\Application\DTO\TranscodeProcessReportDTO;
use App\Application\DTO\TranscodeReportDTO;
use App\Application\Factory\FlashNotificationFactory;
use Psr\Log\LogLevel;
use App\Application\Logging\LogServiceInterface;
use App\Application\Service\Task\TaskRealtimeNotifier;
use App\Application\Service\Task\TranscodeTaskFinalizationService;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Infrastructure\Task\TaskCancellationTrigger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\UuidV4;

class TranscodeTaskFinalizationServiceTest extends TestCase
{
    public function testHandleCancellationUsesFreshTaskAndPersistsCancelledReport(): void
    {
        $taskId = UuidV4::fromString('123e4567-e89b-42d3-a456-426614174210');
        $originalTask = $this->createTask($taskId);
        $freshTask = $this->createTask($taskId);
        $report = $this->createReport(true);

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())
            ->method('findByIdFresh')
            ->with($taskId)
            ->willReturn($freshTask);

        $taskRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Task $task) use ($report): bool {
                $meta = $task->meta();

                return $task->status() === TaskStatus::CANCELLED
                    && isset($meta['transcode']['cancelledAt'])
                    && ($meta['transcode']['report'] ?? null) === $report->toArray();
            }));

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())
            ->method('log')
            ->with('task', $taskId, LogLevel::INFO, 'Transcoding cancelled');

        $cancellationTrigger = new TaskCancellationTrigger(new ArrayAdapter());
        $cancellationTrigger->request($taskId);

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));
        $taskRealtimeNotifier = new TaskRealtimeNotifier($commandBus);

        $service = new TranscodeTaskFinalizationService($taskRepository, $logService, $taskRealtimeNotifier, new FlashNotificationFactory(), $cancellationTrigger);
        $service->handleCancellation($originalTask, $report);

        $this->assertFalse($cancellationTrigger->isRequested($taskId));
    }

    public function testHandleSuccessStoresOutputAndCompletesTask(): void
    {
        $taskId = UuidV4::fromString('123e4567-e89b-42d3-a456-426614174211');
        $task = $this->createTask($taskId);
        $task->start(12.5);
        $report = $this->createReport(false);

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Task $savedTask) use ($report): bool {
                $meta = $savedTask->meta();

                return $savedTask->status() === TaskStatus::COMPLETED
                    && $savedTask->progress()->value() === 100
                    && ($meta['output'] ?? null) === 'video/11.mp4'
                    && isset($meta['transcode']['finishedAt'])
                    && ($meta['transcode']['report'] ?? null) === $report->toArray();
            }));

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())
            ->method('log')
            ->with('task', $taskId, LogLevel::INFO, 'Transcoding finished successfully');

        $cancellationTrigger = new TaskCancellationTrigger(new ArrayAdapter());
        $cancellationTrigger->request($taskId);

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));
        $taskRealtimeNotifier = new TaskRealtimeNotifier($commandBus);

        $service = new TranscodeTaskFinalizationService($taskRepository, $logService, $taskRealtimeNotifier, new FlashNotificationFactory(), $cancellationTrigger);
        $service->handleSuccess($task, 'video/11.mp4', $report);

        $this->assertFalse($cancellationTrigger->isRequested($taskId));
    }

    public function testHandleFailureFailsActiveTaskAndLogsError(): void
    {
        $taskId = UuidV4::fromString('123e4567-e89b-42d3-a456-426614174212');
        $task = $this->createTask($taskId);

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(static fn (Task $savedTask): bool => $savedTask->status() === TaskStatus::FAILED));

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())
            ->method('log')
            ->with('task', $taskId, LogLevel::ERROR, 'Transcoding failed', ['message' => 'boom']);

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));
        $taskRealtimeNotifier = new TaskRealtimeNotifier($commandBus);

        $service = new TranscodeTaskFinalizationService($taskRepository, $logService, $taskRealtimeNotifier, new FlashNotificationFactory(), new TaskCancellationTrigger(new ArrayAdapter()));
        $service->handleFailure($task, new \RuntimeException('boom'));
    }

    private function createTask(UuidV4 $id): Task
    {
        $task = Task::create(
            UuidV4::fromString('123e4567-e89b-42d3-a456-426614174199'),
            UuidV4::fromString('123e4567-e89b-42d3-a456-426614174001'),
            UuidV4::fromString('123e4567-e89b-42d3-a456-426614174007')
        );
        $task->assignId($id);

        return $task;
    }

    private function createReport(bool $cancelled): TranscodeReportDTO
    {
        return new TranscodeReportDTO(
            cancelled: $cancelled,
            ffmpeg: [
                'progress' => $cancelled ? 'cancelled' : 'end',
            ],
            process: new TranscodeProcessReportDTO(
                runtimeSec: 1.234,
                exitCode: $cancelled ? 255 : 0,
                exitCodeText: $cancelled ? 'SIGTERM' : 'OK',
                command: 'ffmpeg -i in out',
                stderrTail: 'stderr',
                stdoutTail: 'stdout',
            ),
        );
    }
}



