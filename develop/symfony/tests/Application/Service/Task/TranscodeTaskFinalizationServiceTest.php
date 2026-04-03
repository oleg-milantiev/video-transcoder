<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Task;

use App\Application\DTO\TranscodeProcessReportDTO;
use App\Application\DTO\TranscodeReportDTO;
use App\Application\DTO\TranscodeStartContextDTO;
use App\Application\Factory\FlashNotificationFactory;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskDates;
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
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Tests\Domain\Entity\VideoFake;
use App\Tests\Domain\Entity\PresetFake;

class TranscodeTaskFinalizationServiceTest extends TestCase
{
    public function testHandleCancellationUsesFreshTaskAndPersistsCancelledReport(): void
    {
        $taskId = Uuid::fromString('123e4567-e89b-42d3-a456-426614174210');
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
            ->with('task', 'cancel', $taskId, LogLevel::INFO, 'Transcoding cancelled');

        $cancellationTrigger = new TaskCancellationTrigger(new ArrayAdapter());
        $cancellationTrigger->request($taskId);

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));
        $taskRealtimeNotifier = new TaskRealtimeNotifier($commandBus, $this->createStub(PresetRepositoryInterface::class), $this->createStub(VideoRepositoryInterface::class));

        $service = new TranscodeTaskFinalizationService($taskRepository, $logService, $taskRealtimeNotifier, new FlashNotificationFactory(), $cancellationTrigger);
        $service->handleCancellation($originalTask, $report);

        $this->assertFalse($cancellationTrigger->isRequested($taskId));
    }

    public function testHandleSuccessStoresOutputAndCompletesTask(): void
    {
        $taskId = Uuid::fromString('123e4567-e89b-42d3-a456-426614174211');
        $task = $this->createTask($taskId);
        $task->start(12.5);
        $report = $this->createReport(false);

        // Create a temp output file
        $tmpOutputFile = tempnam(sys_get_temp_dir(), 'transcode_success_');

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
            ->with('task', 'complete', $taskId, LogLevel::INFO, 'Transcoding finished successfully');

        $cancellationTrigger = new TaskCancellationTrigger(new ArrayAdapter());
        $cancellationTrigger->request($taskId);

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));
        $taskRealtimeNotifier = new TaskRealtimeNotifier($commandBus, $this->createStub(PresetRepositoryInterface::class), $this->createStub(VideoRepositoryInterface::class));

        $context = new TranscodeStartContextDTO(
            task: $task,
            video: VideoFake::create(),
            preset: new PresetFake(),
            relativeOutputPath: 'video/11.mp4',
            absoluteOutputPath: $tmpOutputFile,
            inputPath: '/tmp/input.mp4',
        );

        $service = new TranscodeTaskFinalizationService($taskRepository, $logService, $taskRealtimeNotifier, new FlashNotificationFactory(), $cancellationTrigger);
        $service->handleSuccess($task, $context , $report);

        $this->assertFalse($cancellationTrigger->isRequested($taskId));

        // Cleanup
        @unlink($tmpOutputFile);
    }

    public function testHandleFailureFailsActiveTaskAndLogsError(): void
    {
        $taskId = Uuid::fromString('123e4567-e89b-42d3-a456-426614174212');
        $task = $this->createTask($taskId);

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(static fn (Task $savedTask): bool => $savedTask->status() === TaskStatus::FAILED));

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())
            ->method('log')
            ->with('task', 'fail', $taskId, LogLevel::ERROR, 'Transcoding failed', ['message' => 'boom']);

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));
        $taskRealtimeNotifier = new TaskRealtimeNotifier($commandBus, $this->createStub(PresetRepositoryInterface::class), $this->createStub(VideoRepositoryInterface::class));

        $service = new TranscodeTaskFinalizationService($taskRepository, $logService, $taskRealtimeNotifier, new FlashNotificationFactory(), new TaskCancellationTrigger(new ArrayAdapter()));
        $context = new TranscodeStartContextDTO(
            task: $task,
            video: VideoFake::create(),
            preset: new PresetFake(),
            relativeOutputPath: 'output/test.mp4',
            absoluteOutputPath: '/tmp/output/test.mp4',
            inputPath: '/tmp/input.mp4',
        );
        $service->handleFailure($task, new \RuntimeException('boom'), $context->absoluteOutputPath);
    }

    public function testHandleFailureSkipsSaveWhenTaskAlreadyFinished(): void
    {
        $taskId = Uuid::fromString('123e4567-e89b-42d3-a456-426614174213');
        $task = $this->createTask($taskId);
        // Complete the task so isFinished() returns true
        $task->start(10.0);
        $task->updateProgress(new Progress(100)); // → COMPLETED

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->never())->method('save');

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())->method('log');

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->never())->method('dispatch');
        $taskRealtimeNotifier = new TaskRealtimeNotifier($commandBus, $this->createStub(PresetRepositoryInterface::class), $this->createStub(VideoRepositoryInterface::class));

        $service = new TranscodeTaskFinalizationService($taskRepository, $logService, $taskRealtimeNotifier, new FlashNotificationFactory(), new TaskCancellationTrigger(new ArrayAdapter()));

        $context = new TranscodeStartContextDTO(
            task: $task,
            video: VideoFake::create(),
            preset: new PresetFake(),
            relativeOutputPath: 'output/test.mp4',
            absoluteOutputPath: '/tmp/not_exists_xyz.mp4',
            inputPath: '/tmp/input.mp4',
        );
        $service->handleFailure($task, new \RuntimeException('already finished'), $context->absoluteOutputPath);
    }

    public function testHandleFailureDeletesOutputFileWhenExists(): void
    {
        $taskId = Uuid::fromString('123e4567-e89b-42d3-a456-426614174214');
        $task = $this->createTask($taskId);

        // Create a temp file to simulate existing output
        $tmpFile = tempnam(sys_get_temp_dir(), 'transcode_test_');

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())->method('save');

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())->method('log');

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $taskRealtimeNotifier = new TaskRealtimeNotifier($commandBus, $this->createStub(PresetRepositoryInterface::class), $this->createStub(VideoRepositoryInterface::class));

        $service = new TranscodeTaskFinalizationService($taskRepository, $logService, $taskRealtimeNotifier, new FlashNotificationFactory(), new TaskCancellationTrigger(new ArrayAdapter()));

        $context = new TranscodeStartContextDTO(
            task: $task,
            video: VideoFake::create(),
            preset: new PresetFake(),
            relativeOutputPath: 'output/test.mp4',
            absoluteOutputPath: $tmpFile,
            inputPath: '/tmp/input.mp4',
        );
        $service->handleFailure($task, new \RuntimeException('fail with file'), $context->absoluteOutputPath);

        $this->assertFileDoesNotExist($tmpFile);
    }

    private function createTask(Uuid $id): Task
    {
        return Task::reconstitute(
            videoId: Uuid::fromString('123e4567-e89b-42d3-a456-426614174199'),
            presetId: Uuid::fromString('123e4567-e89b-42d3-a456-426614174001'),
            userId: Uuid::fromString('123e4567-e89b-42d3-a456-426614174007'),
            status: TaskStatus::STARTING,
            progress: new Progress(0),
            dates: TaskDates::create(),
            id: $id,
        );
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
