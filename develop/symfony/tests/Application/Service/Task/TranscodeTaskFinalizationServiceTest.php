<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Task;

use App\Application\DTO\TranscodeProcessReportDTO;
use App\Application\DTO\TranscodeReportDTO;
use App\Application\DTO\TranscodeStartContextDTO;
use App\Application\Factory\FlashNotificationFactory;
use App\Application\Logging\LogServiceInterface;
use App\Application\Service\Storage\StorageRealtimeNotifier;
use App\Application\Service\Task\TaskRealtimeNotifier;
use App\Application\Service\Task\TranscodeTaskFinalizationService;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\ValueObject\AudioCodec;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\Format;
use App\Domain\Video\ValueObject\PresetTitle;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskDates;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoCodec;
use App\Domain\Video\ValueObject\VideoTitle;
use App\Infrastructure\Task\TaskCancellationTrigger;
use App\Tests\Domain\Entity\PresetFake;
use App\Tests\Domain\Entity\VideoFake;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class TranscodeTaskFinalizationServiceTest extends TestCase
{
    // Fixed IDs matching createTask()
    private const VIDEO_ID  = '123e4567-e89b-42d3-a456-426614174199';
    private const PRESET_ID = '123e4567-e89b-42d3-a456-426614174001';

    private function makeNotifier(MessageBusInterface $bus): TaskRealtimeNotifier
    {
        $video = Video::reconstitute(
            title: new VideoTitle('Task Video'),
            extension: new FileExtension('mp4'),
            userId: Uuid::fromString('123e4567-e89b-42d3-a456-426614174007'),
            meta: [],
            dates: VideoDates::create(),
            id: Uuid::fromString(self::VIDEO_ID),
        );
        $preset = new Preset(
            title: new PresetTitle('HD 720p'),
            videoCodec: new VideoCodec('h264'),
            audioCodec: new AudioCodec('aac'),
            format: new Format('mp4'),
            id: Uuid::fromString(self::PRESET_ID),
        );

        $videoRepo = $this->createStub(VideoRepositoryInterface::class);
        $videoRepo->method('findById')->willReturn($video);
        $presetRepo = $this->createStub(PresetRepositoryInterface::class);
        $presetRepo->method('findById')->willReturn($preset);

        return new TaskRealtimeNotifier($bus, $presetRepo, $videoRepo);
    }

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
                    && !array_key_exists('sizeExpected', $meta)
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
        $taskRealtimeNotifier = $this->makeNotifier($commandBus);

        $service = new TranscodeTaskFinalizationService($taskRepository, $logService, $taskRealtimeNotifier, new FlashNotificationFactory(), $cancellationTrigger, $this->createStub(StorageRealtimeNotifier::class), new Filesystem());

        $context = new TranscodeStartContextDTO(
            task: $originalTask,
            video: VideoFake::create(),
            preset: new PresetFake(),
            relativeOutputPath: 'video/output.mp4',
            absoluteOutputPath: '/tmp/output.mp4',
            inputPath: '/tmp/input.mp4',
            timeStart: 0.0,
        );
        $service->handleCancellation($context, $report);

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
            ->with('task', 'transcode', $taskId, LogLevel::INFO, 'Transcoding finished successfully', $this->callback(function (array $context): bool {
                return isset($context['time']) && is_float($context['time']) && isset($context['size']);
            }));

        $cancellationTrigger = new TaskCancellationTrigger(new ArrayAdapter());
        $cancellationTrigger->request($taskId);

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));
        $taskRealtimeNotifier = $this->makeNotifier($commandBus);

        $context = new TranscodeStartContextDTO(
            task: $task,
            video: VideoFake::create(),
            preset: new PresetFake(),
            relativeOutputPath: 'video/11.mp4',
            absoluteOutputPath: $tmpOutputFile,
            inputPath: '/tmp/input.mp4',
            timeStart: 0.0,
        );

        $service = new TranscodeTaskFinalizationService($taskRepository, $logService, $taskRealtimeNotifier, new FlashNotificationFactory(), $cancellationTrigger, $this->createStub(StorageRealtimeNotifier::class), new Filesystem());
        $service->handleSuccess($context, $report);

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
            ->with('task', 'transcode', $taskId, LogLevel::ERROR, 'Transcoding failed', ['message' => 'boom']);

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));
        $taskRealtimeNotifier = $this->makeNotifier($commandBus);

        $service = new TranscodeTaskFinalizationService($taskRepository, $logService, $taskRealtimeNotifier, new FlashNotificationFactory(), new TaskCancellationTrigger(new ArrayAdapter()), $this->createStub(StorageRealtimeNotifier::class), new Filesystem());
        $context = new TranscodeStartContextDTO(
            task: $task,
            video: VideoFake::create(),
            preset: new PresetFake(),
            relativeOutputPath: 'output/test.mp4',
            absoluteOutputPath: '/tmp/output/test.mp4',
            inputPath: '/tmp/input.mp4',
            timeStart: 0.0,
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
        $taskRealtimeNotifier = $this->makeNotifier($commandBus);

        $service = new TranscodeTaskFinalizationService($taskRepository, $logService, $taskRealtimeNotifier, new FlashNotificationFactory(), new TaskCancellationTrigger(new ArrayAdapter()), $this->createStub(StorageRealtimeNotifier::class), new Filesystem());

        $context = new TranscodeStartContextDTO(
            task: $task,
            video: VideoFake::create(),
            preset: new PresetFake(),
            relativeOutputPath: 'output/test.mp4',
            absoluteOutputPath: '/tmp/not_exists_xyz.mp4',
            inputPath: '/tmp/input.mp4',
            timeStart: 0.0,
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
        $taskRealtimeNotifier = $this->makeNotifier($commandBus);

        $service = new TranscodeTaskFinalizationService($taskRepository, $logService, $taskRealtimeNotifier, new FlashNotificationFactory(), new TaskCancellationTrigger(new ArrayAdapter()), $this->createStub(StorageRealtimeNotifier::class), new Filesystem());

        $context = new TranscodeStartContextDTO(
            task: $task,
            video: VideoFake::create(),
            preset: new PresetFake(),
            relativeOutputPath: 'output/test.mp4',
            absoluteOutputPath: $tmpFile,
            inputPath: '/tmp/input.mp4',
            timeStart: 0.0,
        );
        $service->handleFailure($task, new \RuntimeException('fail with file'), $context->absoluteOutputPath);

        $this->assertFileDoesNotExist($tmpFile);
    }

    private function createTask(Uuid $id): Task
    {
        return Task::reconstitute(
            videoId: Uuid::fromString(self::VIDEO_ID),
            presetId: Uuid::fromString(self::PRESET_ID),
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
