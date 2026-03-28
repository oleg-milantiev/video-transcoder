<?php

namespace App\Application\Service\Task;

use App\Application\DTO\TranscodeProcessReportDTO;
use App\Application\DTO\TranscodeReportDTO;
use App\Application\DTO\TranscodeStartContextDTO;
use Psr\Log\LogLevel;
use App\Application\Logging\LogServiceInterface;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\ValueObject\Progress;
use App\Infrastructure\Ffmpeg\ProcessRunnerInterface;
use App\Infrastructure\Ffmpeg\Transcode;
use App\Infrastructure\Task\TaskCancellationTrigger;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

readonly class TranscodeProcessService
{
    private const float PROGRESS_UPDATE_INTERVAL = 5.0;
    private const float CANCEL_CHECK_INTERVAL = 1.0;
    private const int PROCESS_LOG_TAIL_LENGTH = 8000;

    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private LogServiceInterface $logService,
        private TaskRealtimeNotifier $taskRealtimeNotifier,
        private TaskCancellationTrigger $cancellationTrigger,
        private ProcessRunnerInterface $processRunner,
    ) {
    }

    public function run(TranscodeStartContextDTO $context): TranscodeReportDTO
    {
        $task = $context->task;
        $duration = $context->video->duration();
        $command = Transcode::buildCommand($context->inputPath, $context->absoluteOutputPath, $context->preset);

        $buffer = '';
        $ffmpegStats = [];
        $stderrTail = '';
        $stdoutTail = '';
        $lastPersistAt = microtime(true);
        $lastProgressValue = $task->progress()->value();
        $lastCancelCheckAt = 0.0;
        $startedAt = microtime(true);
        $cancelled = false;

        $process = $this->processRunner->runStreaming($command, function (string $type, string $data, Process $process) use ($duration, $task, &$buffer, &$lastPersistAt, &$lastProgressValue, &$ffmpegStats, &$stderrTail, &$stdoutTail, &$lastCancelCheckAt, &$cancelled) {
            if ($type !== Process::OUT && $type !== Process::ERR) {
                return;
            }

            if ($this->shouldCheckCancellation($lastCancelCheckAt) && $this->isCancellationRequested($task)) {
                $cancelled = true;
                $process->stop(1);
                return;
            }

            if ($type === Process::ERR) {
                $stderrTail = $this->appendLogTail($stderrTail, $data);
            } else {
                $stdoutTail = $this->appendLogTail($stdoutTail, $data);
            }

            $buffer .= $data;

            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $newlinePos));
                $buffer = substr($buffer, $newlinePos + 1);

                $equalsPos = strpos($line, '=');
                if ($equalsPos !== false) {
                    $key = substr($line, 0, $equalsPos);
                    $value = substr($line, $equalsPos + 1);
                    if ($key !== '') {
                        $ffmpegStats[$key] = $value;
                    }
                }

                if ($line === '' || strncmp($line, 'out_time_ms=', 12) !== 0 || !$duration || $duration <= 0.0) {
                    continue;
                }

                $outTimeMs = (int) substr($line, 12);
                $seconds = $outTimeMs / 1_000_000;
                $progressValue = (int) min(99, max(0, floor(($seconds / $duration) * 100)));

                if ($this->shouldPersistProgress($lastPersistAt, $lastProgressValue, $progressValue)) {
                    $this->persistProgress($task, $progressValue);
                    $lastProgressValue = $progressValue;
                    $lastPersistAt = microtime(true);
                }
            }
        });

        $report = new TranscodeReportDTO(
            cancelled: $cancelled,
            ffmpeg: $ffmpegStats,
            process: new TranscodeProcessReportDTO(
                runtimeSec: round(microtime(true) - $startedAt, 3),
                exitCode: $process->getExitCode(),
                exitCodeText: $process->getExitCodeText() ?? '',
                command: implode(' ', $command),
                stderrTail: $stderrTail,
                stdoutTail: $stdoutTail,
            ),
        );

        if ($cancelled) {
            return $report;
        }

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $report;
    }

    private function shouldPersistProgress(float $lastPersistAt, int $lastProgress, int $nextProgress): bool
    {
        if ($nextProgress <= $lastProgress) {
            return false;
        }

        if ($nextProgress >= 99) {
            return true;
        }

        return (microtime(true) - $lastPersistAt) >= self::PROGRESS_UPDATE_INTERVAL;
    }

    private function persistProgress(Task $task, int $value): void
    {
        $task->updateProgress(new Progress($value));
        $this->taskRepository->save($task);
        $this->logService->log('task', $task->id(), LogLevel::INFO, 'Transcoding progress', [
            'progress' => $value,
        ]);
        $this->taskRealtimeNotifier->notifyTaskUpdated($task, 'progress');
    }

    private function appendLogTail(string $tail, string $chunk): string
    {
        $merged = $tail . $chunk;
        if (strlen($merged) <= self::PROCESS_LOG_TAIL_LENGTH) {
            return $merged;
        }

        return substr($merged, -self::PROCESS_LOG_TAIL_LENGTH);
    }

    private function shouldCheckCancellation(float &$lastCheckAt): bool
    {
        $now = microtime(true);
        if (($now - $lastCheckAt) < self::CANCEL_CHECK_INTERVAL) {
            return false;
        }

        $lastCheckAt = $now;

        return true;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function isCancellationRequested(Task $task): bool
    {
        return $this->cancellationTrigger->isRequested($task->id());
    }
}

