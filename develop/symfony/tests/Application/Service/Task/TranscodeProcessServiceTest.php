<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Task;

use App\Application\DTO\TranscodeStartContextDTO;
use App\Application\Service\Task\TranscodeProcessService;
use App\Application\Logging\LogServiceInterface;
use App\Application\Service\Task\TaskRealtimeNotifier;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Infrastructure\Ffmpeg\ProcessRunnerInterface;
use App\Infrastructure\Task\TaskCancellationTrigger;
use App\Tests\Domain\Entity\TaskFake;
use App\Tests\Domain\Entity\VideoFake;
use App\Tests\Domain\Entity\PresetFake;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;

final class TranscodeProcessServiceTest extends TestCase
{
    public function testRunPersistsProgressAndReturnsReport(): void
    {
        $task = TaskFake::create();
        $video = VideoFake::create();
        // Ensure video has a duration so progress can be calculated
        $video->updateMeta(['duration' => 10.0]);
        $preset = new PresetFake();

        $task->start($video->duration());
        $context = new TranscodeStartContextDTO(
            task: $task,
            video: $video,
            preset: $preset,
            relativeOutputPath: 'output/test.mp4',
            absoluteOutputPath: '/tmp/output/test.mp4',
            inputPath: '/tmp/input.mp4',
        );

        $saved = false;

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function ($savedTask) use (&$saved): bool {
                $saved = true;
                // progress should have been updated to 99 (service caps to 99)
                return (int) $savedTask->progress()->value() === 99;
            }));

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())
            ->method('log')
            ->with('task', 'progress', $task->id(), LogLevel::INFO, 'Transcoding progress', ['progress' => 99]);

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())->method('dispatch')->willReturn(new \Symfony\Component\Messenger\Envelope(new \stdClass()));
        $taskRealtimeNotifier = new TaskRealtimeNotifier($commandBus, $this->createStub(PresetRepositoryInterface::class), $this->createStub(VideoRepositoryInterface::class));

        // Process mock returned by runner
        $processMock = $this->createStub(Process::class);
        $processMock->method('isSuccessful')->willReturn(true);
        $processMock->method('getExitCode')->willReturn(0);
        $processMock->method('getExitCodeText')->willReturn('OK');

        // Runner that streams a single out_time_ms line that yields 99%
        $runner = new class($processMock) implements ProcessRunnerInterface {
            public function __construct(private Process $proc) {}

            public function mustRun(array $command): void
            {
                // not used in these tests
            }

            public function mustRunAndGetOutput(array $command): string
            {
                return '';
            }

            public function runStreaming(array $command, callable $onData): Process
            {
                // Simulate ffmpeg progress line: out_time_ms -> microseconds
                // For video duration 10s, sending out_time_ms=10000000 -> seconds=10 -> progress -> 99 (capped)
                $onData(Process::OUT, "out_time_ms=10000000\n", $this->proc);
                return $this->proc;
            }
        };

        $cancellationTrigger = new TaskCancellationTrigger(new ArrayAdapter());

        $service = new TranscodeProcessService($taskRepository, $logService, $taskRealtimeNotifier, $cancellationTrigger, $runner);

        $report = $service->run($context);

        $this->assertFalse($report->cancelled);
        $this->assertSame(0, $report->process->exitCode);
        $this->assertTrue($saved, 'TaskRepository::save should be called to persist progress');
    }

    public function testRunStopsWhenCancellationRequested(): void
    {
        $task = TaskFake::create();
        $video = VideoFake::create();
        $video->updateMeta(['duration' => 5.0]);
        $preset = new PresetFake();

        $task->start($video->duration());
        $context = new TranscodeStartContextDTO(
            task: $task,
            video: $video,
            preset: $preset,
            relativeOutputPath: 'output/test.mp4',
            absoluteOutputPath: '/tmp/output/test.mp4',
            inputPath: '/tmp/input.mp4',
        );

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        // no save expectation here

        $logService = $this->createStub(LogServiceInterface::class);

        $commandBus = $this->createStub(MessageBusInterface::class);
        $taskRealtimeNotifier = new TaskRealtimeNotifier($commandBus, $this->createStub(PresetRepositoryInterface::class), $this->createStub(VideoRepositoryInterface::class));

        $processMock = $this->createMock(Process::class);
        // Expect stop to be called due to cancellation
        $processMock->expects($this->once())->method('stop')->with(1);
        $processMock->method('isSuccessful')->willReturn(true);
        $processMock->method('getExitCode')->willReturn(0);
        $processMock->method('getExitCodeText')->willReturn('OK');

        $runner = new class($processMock) implements ProcessRunnerInterface {
            public function __construct(private Process $proc) {}
            public function mustRun(array $command): void {}
            public function mustRunAndGetOutput(array $command): string { return ''; }
            public function runStreaming(array $command, callable $onData): Process
            {
                // Trigger callback once to allow cancellation check to run
                $onData(Process::OUT, "progress=1\n", $this->proc);
                return $this->proc;
            }
        };

        $cancellationTrigger = new TaskCancellationTrigger(new ArrayAdapter());
        // Request cancellation before running — service should observe this on first check
        $cancellationTrigger->request($task->id());

        $service = new TranscodeProcessService($taskRepository, $logService, $taskRealtimeNotifier, $cancellationTrigger, $runner);

        $report = $service->run($context);

        $this->assertTrue($report->cancelled);
    }

    public function testRunThrowsProcessFailedExceptionWhenProcessIsNotSuccessful(): void
    {
        $this->expectException(ProcessFailedException::class);

        $task = TaskFake::create();
        $video = VideoFake::create();
        $video->updateMeta(['duration' => 3.0]);
        $preset = new PresetFake();

        $task->start($video->duration());
        $context = new TranscodeStartContextDTO(
            task: $task,
            video: $video,
            preset: $preset,
            relativeOutputPath: 'output/test.mp4',
            absoluteOutputPath: '/tmp/output/test.mp4',
            inputPath: '/tmp/input.mp4',
        );

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $logService = $this->createStub(LogServiceInterface::class);
        $commandBus = $this->createStub(MessageBusInterface::class);
        $taskRealtimeNotifier = new TaskRealtimeNotifier($commandBus, $this->createStub(PresetRepositoryInterface::class), $this->createStub(VideoRepositoryInterface::class));

        $processMock = $this->createStub(Process::class);
        $processMock->method('isSuccessful')->willReturn(false);
        $processMock->method('getExitCode')->willReturn(1);
        $processMock->method('getExitCodeText')->willReturn('ERR');

        $runner = new class($processMock) implements ProcessRunnerInterface {
            public function __construct(private Process $proc) {}
            public function mustRun(array $command): void {}
            public function mustRunAndGetOutput(array $command): string { return ''; }
            public function runStreaming(array $command, callable $onData): Process
            {
                // deliver no progress lines, just return a failed process
                return $this->proc;
            }
        };

        $service = new TranscodeProcessService($taskRepository, $logService, $taskRealtimeNotifier, new TaskCancellationTrigger(new ArrayAdapter()), $runner);

        $service->run($context);
    }

    public function testErrOutputIsCollectedInStderrTail(): void
    {
        $task = TaskFake::create();
        $video = VideoFake::create();
        $video->updateMeta(['duration' => 10.0]);
        $preset = new PresetFake();
        $task->start($video->duration());

        $context = new TranscodeStartContextDTO(
            task: $task, video: $video, preset: $preset,
            relativeOutputPath: 'output/test.mp4',
            absoluteOutputPath: '/tmp/output/test.mp4',
            inputPath: '/tmp/input.mp4',
        );

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $logService = $this->createStub(LogServiceInterface::class);
        $commandBus = $this->createStub(MessageBusInterface::class);
        $taskRealtimeNotifier = new TaskRealtimeNotifier($commandBus, $this->createStub(PresetRepositoryInterface::class), $this->createStub(VideoRepositoryInterface::class));

        $processMock = $this->createStub(Process::class);
        $processMock->method('isSuccessful')->willReturn(true);
        $processMock->method('getExitCode')->willReturn(0);
        $processMock->method('getExitCodeText')->willReturn('OK');

        $runner = new class($processMock) implements ProcessRunnerInterface {
            public function __construct(private Process $proc) {}
            public function mustRun(array $command): void {}
            public function mustRunAndGetOutput(array $command): string { return ''; }
            public function runStreaming(array $command, callable $onData): Process
            {
                // ERR type → covers the stderrTail branch in run()
                $onData(Process::ERR, "frame=  10 fps=25\n", $this->proc);
                return $this->proc;
            }
        };

        $service = new TranscodeProcessService($taskRepository, $logService, $taskRealtimeNotifier, new TaskCancellationTrigger(new ArrayAdapter()), $runner);
        $report = $service->run($context);

        $this->assertFalse($report->cancelled);
        $this->assertStringContainsString('frame=', $report->process->stderrTail);
    }

    public function testAppendLogTailTruncatesWhenDataExceedsLimit(): void
    {
        $task = TaskFake::create();
        $video = VideoFake::create();
        $video->updateMeta(['duration' => 10.0]);
        $preset = new PresetFake();
        $task->start($video->duration());

        $context = new TranscodeStartContextDTO(
            task: $task, video: $video, preset: $preset,
            relativeOutputPath: 'output/test.mp4',
            absoluteOutputPath: '/tmp/output/test.mp4',
            inputPath: '/tmp/input.mp4',
        );

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $logService = $this->createStub(LogServiceInterface::class);
        $commandBus = $this->createStub(MessageBusInterface::class);
        $taskRealtimeNotifier = new TaskRealtimeNotifier($commandBus, $this->createStub(PresetRepositoryInterface::class), $this->createStub(VideoRepositoryInterface::class));

        $processMock = $this->createStub(Process::class);
        $processMock->method('isSuccessful')->willReturn(true);
        $processMock->method('getExitCode')->willReturn(0);
        $processMock->method('getExitCodeText')->willReturn('OK');

        $suffix = 'END_MARKER';
        $oversizedData = str_repeat('x', 9000) . $suffix; // > 8000 char limit

        $runner = new class($processMock, $oversizedData) implements ProcessRunnerInterface {
            public function __construct(private Process $proc, private string $data) {}
            public function mustRun(array $command): void {}
            public function mustRunAndGetOutput(array $command): string { return ''; }
            public function runStreaming(array $command, callable $onData): Process
            {
                // Send >8000 chars via ERR to trigger appendLogTail truncation path
                $onData(Process::ERR, $this->data, $this->proc);
                return $this->proc;
            }
        };

        $service = new TranscodeProcessService($taskRepository, $logService, $taskRealtimeNotifier, new TaskCancellationTrigger(new ArrayAdapter()), $runner);
        $report = $service->run($context);

        $this->assertFalse($report->cancelled);
        $this->assertSame(8000, strlen($report->process->stderrTail));
        $this->assertStringEndsWith($suffix, $report->process->stderrTail);
    }

    public function testShouldCheckCancellationReturnsFalseWithinInterval(): void
    {
        $task = TaskFake::create();
        $video = VideoFake::create();
        $video->updateMeta(['duration' => 10.0]);
        $preset = new PresetFake();
        $task->start($video->duration());

        $context = new TranscodeStartContextDTO(
            task: $task, video: $video, preset: $preset,
            relativeOutputPath: 'output/test.mp4',
            absoluteOutputPath: '/tmp/output/test.mp4',
            inputPath: '/tmp/input.mp4',
        );

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $logService = $this->createStub(LogServiceInterface::class);
        $commandBus = $this->createStub(MessageBusInterface::class);
        $taskRealtimeNotifier = new TaskRealtimeNotifier($commandBus, $this->createStub(PresetRepositoryInterface::class), $this->createStub(VideoRepositoryInterface::class));

        $processMock = $this->createStub(Process::class);
        $processMock->method('isSuccessful')->willReturn(true);
        $processMock->method('getExitCode')->willReturn(0);
        $processMock->method('getExitCodeText')->willReturn('OK');

        $runner = new class($processMock) implements ProcessRunnerInterface {
            public function __construct(private Process $proc) {}
            public function mustRun(array $command): void {}
            public function mustRunAndGetOutput(array $command): string { return ''; }
            public function runStreaming(array $command, callable $onData): Process
            {
                // Two consecutive chunks: first check fires, second is within 1s interval → returns false
                $onData(Process::OUT, "progress=1\n", $this->proc);
                $onData(Process::OUT, "progress=2\n", $this->proc);
                return $this->proc;
            }
        };

        $service = new TranscodeProcessService($taskRepository, $logService, $taskRealtimeNotifier, new TaskCancellationTrigger(new ArrayAdapter()), $runner);
        $report = $service->run($context);

        $this->assertFalse($report->cancelled);
    }

    public function testUnknownOutputTypeIsIgnoredWithEarlyReturn(): void
    {
        $task = TaskFake::create();
        $video = VideoFake::create();
        $video->updateMeta(['duration' => 10.0]);
        $preset = new PresetFake();
        $task->start($video->duration());

        $context = new TranscodeStartContextDTO(
            task: $task, video: $video, preset: $preset,
            relativeOutputPath: 'output/test.mp4',
            absoluteOutputPath: '/tmp/output/test.mp4',
            inputPath: '/tmp/input.mp4',
        );

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $logService = $this->createStub(LogServiceInterface::class);
        $commandBus = $this->createStub(MessageBusInterface::class);
        $taskRealtimeNotifier = new TaskRealtimeNotifier($commandBus, $this->createStub(PresetRepositoryInterface::class), $this->createStub(VideoRepositoryInterface::class));

        $processMock = $this->createStub(Process::class);
        $processMock->method('isSuccessful')->willReturn(true);
        $processMock->method('getExitCode')->willReturn(0);
        $processMock->method('getExitCodeText')->willReturn('OK');

        $runner = new class($processMock) implements ProcessRunnerInterface {
            public function __construct(private Process $proc) {}
            public function mustRun(array $command): void {}
            public function mustRunAndGetOutput(array $command): string { return ''; }
            public function runStreaming(array $command, callable $onData): Process
            {
                // Send a type that is neither OUT nor ERR → triggers the early return on line 53
                $onData('unknown_type', "ignored data\n", $this->proc);
                return $this->proc;
            }
        };

        $service = new TranscodeProcessService($taskRepository, $logService, $taskRealtimeNotifier, new TaskCancellationTrigger(new ArrayAdapter()), $runner);
        $report = $service->run($context);

        $this->assertFalse($report->cancelled);
        // Neither tail should contain data since the callback returned early
        $this->assertSame('', $report->process->stderrTail);
        $this->assertSame('', $report->process->stdoutTail);
    }

    public function testShouldPersistProgressEvaluatesTimeBasedCondition(): void
    {
        $task = TaskFake::create();
        $video = VideoFake::create();
        $video->updateMeta(['duration' => 10.0]);
        $preset = new PresetFake();
        $task->start($video->duration());

        $context = new TranscodeStartContextDTO(
            task: $task, video: $video, preset: $preset,
            relativeOutputPath: 'output/test.mp4',
            absoluteOutputPath: '/tmp/output/test.mp4',
            inputPath: '/tmp/input.mp4',
        );

        // save() must NOT be called — not enough time elapsed and progress is between 1–98
        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->never())->method('save');

        $logService = $this->createStub(LogServiceInterface::class);
        $commandBus = $this->createStub(MessageBusInterface::class);
        $taskRealtimeNotifier = new TaskRealtimeNotifier($commandBus, $this->createStub(PresetRepositoryInterface::class), $this->createStub(VideoRepositoryInterface::class));

        $processMock = $this->createStub(Process::class);
        $processMock->method('isSuccessful')->willReturn(true);
        $processMock->method('getExitCode')->willReturn(0);
        $processMock->method('getExitCodeText')->willReturn('OK');

        $runner = new class($processMock) implements ProcessRunnerInterface {
            public function __construct(private Process $proc) {}
            public function mustRun(array $command): void {}
            public function mustRunAndGetOutput(array $command): string { return ''; }
            public function runStreaming(array $command, callable $onData): Process
            {
                // 1 second / 10 second duration = 10% progress
                // 10 > lastProgress(0) and 10 < 99 → the time-based return on line 133 is reached
                // returns false (< 5 seconds elapsed) so no persist
                $onData(Process::OUT, "out_time_ms=1000000\n", $this->proc);
                return $this->proc;
            }
        };

        $service = new TranscodeProcessService($taskRepository, $logService, $taskRealtimeNotifier, new TaskCancellationTrigger(new ArrayAdapter()), $runner);
        $report = $service->run($context);

        $this->assertFalse($report->cancelled);
    }

    public function testShouldPersistProgressReturnsFalseWhenProgressUnchanged(): void
    {
        $task = TaskFake::create();
        $video = VideoFake::create();
        $video->updateMeta(['duration' => 10.0]);
        $preset = new PresetFake();
        $task->start($video->duration());

        $context = new TranscodeStartContextDTO(
            task: $task, video: $video, preset: $preset,
            relativeOutputPath: 'output/test.mp4',
            absoluteOutputPath: '/tmp/output/test.mp4',
            inputPath: '/tmp/input.mp4',
        );

        // save() must NOT be called — progress stays at 0, no change to persist
        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->never())->method('save');

        $logService = $this->createStub(LogServiceInterface::class);
        $commandBus = $this->createStub(MessageBusInterface::class);
        $taskRealtimeNotifier = new TaskRealtimeNotifier($commandBus, $this->createStub(PresetRepositoryInterface::class), $this->createStub(VideoRepositoryInterface::class));

        $processMock = $this->createStub(Process::class);
        $processMock->method('isSuccessful')->willReturn(true);
        $processMock->method('getExitCode')->willReturn(0);
        $processMock->method('getExitCodeText')->willReturn('OK');

        $runner = new class($processMock) implements ProcessRunnerInterface {
            public function __construct(private Process $proc) {}
            public function mustRun(array $command): void {}
            public function mustRunAndGetOutput(array $command): string { return ''; }
            public function runStreaming(array $command, callable $onData): Process
            {
                // out_time_ms=0 → progressValue 0 == lastProgressValue 0 → shouldPersistProgress returns false
                $onData(Process::OUT, "out_time_ms=0\n", $this->proc);
                return $this->proc;
            }
        };

        $service = new TranscodeProcessService($taskRepository, $logService, $taskRealtimeNotifier, new TaskCancellationTrigger(new ArrayAdapter()), $runner);
        $report = $service->run($context);

        $this->assertFalse($report->cancelled);
    }
}
