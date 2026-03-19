<?php

namespace App\Application\CommandHandler\Task;

use App\Application\Command\Task\StartTaskScheduler;
use App\Application\Command\Task\TranscodeVideo;
use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Infrastructure\Ffmpeg\Transcode;
use App\Infrastructure\Task\TaskCancellationTrigger;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

// TODO split and test!
#[AsMessageHandler]
final readonly class TranscodeVideoHandler
{
    private const float PROGRESS_UPDATE_INTERVAL = 5.0;
    private const float CANCEL_CHECK_INTERVAL = 1.0;
    private const int TASK_MUTEX_TTL = 7200;
    private const int PROCESS_LOG_TAIL_LENGTH = 8000;

    public function __construct(
        private MessageBusInterface $messageBus,
        private TaskRepositoryInterface $taskRepository,
        private VideoRepositoryInterface $videoRepository,
        private PresetRepositoryInterface $presetRepository,
        private StorageInterface $storage,
        private Filesystem $filesystem,
        private LoggerInterface $logger,
        private LockFactory $lockFactory,
        private TaskCancellationTrigger $cancellationTrigger,
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function __invoke(TranscodeVideo $command): void
    {
        $scheduledTask = $command->scheduledTask;
        $task = $this->taskRepository->findById($scheduledTask->taskId);

        if (!$task) {
            $this->logger->error('Scheduled task not found for transcoding', ['taskId' => $scheduledTask->taskId]);
            return;
        }

        // TODO move mutex to redis
        $lock = $this->lockFactory->createLock(sprintf('transcode-task:%d', $scheduledTask->taskId), self::TASK_MUTEX_TTL);
        $acquired = $lock->acquire();
        if (!$acquired) {
            $this->logger->info('Skipping task because mutex is already acquired by another worker', ['taskId' => $scheduledTask->taskId]);
            return;
        }

        $video = $this->videoRepository->findById($task->videoId());
        if (!$video) {
            $this->taskRepository->log($task->id(), 'error', 'Video not found for transcoding');
            throw new \RuntimeException('Video not found for transcoding');
        }

        if ($this->cancellationTrigger->isRequested($task->id())) {
            if ($task->canBeCancelled()) {
                $task->cancel();
                $task->updateMeta([
                    'cancelledAt' => new \DateTimeImmutable()->format(DATE_ATOM),
                ]);
                $this->taskRepository->save($task);
                $this->taskRepository->log($task->id(), 'info', 'Task cancelled before ffmpeg start');
            }

            $this->cancellationTrigger->clear($task->id());

            return;
        }

        if (!$task->canStart($video->duration())) {
            $this->taskRepository->log($task->id(), 'warning', 'Task cannot be started for transcoding (invalid state or video duration).');
            return;
        }

        try {
            $preset = $this->presetRepository->findById($task->presetId());
            if (!$preset) {
                $this->taskRepository->log($task->id(), 'error', 'Preset not found for task');
                throw new \RuntimeException('Preset not found for task');
            }

            // TODO use abstract storage
            $relativeOutputPath = sprintf('%s/%d.mp4', $video->id()->toRfc4122(), $preset->id());
            $absoluteOutputPath = $this->storage->getAbsolutePath($relativeOutputPath);
            $this->filesystem->mkdir(\dirname($absoluteOutputPath));

            $task->start();
            $this->taskRepository->save($task);
            $this->taskRepository->log($task->id(), 'info', 'Transcoding started');

            $inputPath = $this->storage->getAbsolutePath($video->getSrcFilename());
            $transcodeReport = $this->runTranscodeProcess($inputPath, $absoluteOutputPath, $preset, $video->duration(), $task);

            if (($transcodeReport['cancelled'] ?? false) === true) {
                $cancelledTask = $this->taskRepository->findById($task->id()) ?? $task;
                if ($cancelledTask->status() !== TaskStatus::CANCELLED) {
                    $cancelledTask->cancel();
                }
                $cancelledTask->updateMeta([
                    'transcode' => [
                        'cancelledAt' => new \DateTimeImmutable()->format(DATE_ATOM),
                        'report' => $transcodeReport,
                    ],
                ]);
                $this->taskRepository->save($cancelledTask);
                $this->taskRepository->log($cancelledTask->id(), 'info', 'Transcoding cancelled');
                $this->cancellationTrigger->clear($cancelledTask->id());

                $this->messageBus->dispatch(new StartTaskScheduler());

                return;
            }

            $task->updateProgress(new Progress(100));
            $task->updateMeta([
                'output' => $relativeOutputPath,
                'transcode' => [
                    'finishedAt' => new \DateTimeImmutable()->format(DATE_ATOM),
                    'report' => $transcodeReport,
                ],
            ]);
            $this->taskRepository->save($task);
            $this->taskRepository->log($task->id(), 'info', 'Transcoding finished successfully');
            $this->cancellationTrigger->clear($task->id());
        } catch (\Throwable $exception) {
            if (!$task->status()->isFinished()) {
                $task->fail();
                $this->taskRepository->save($task);
            }
            $this->taskRepository->log($task->id(), 'error', 'Transcoding failed: '. $exception->getMessage());
            $this->logger->error('TranscodeVideoHandler failed', [
                'taskId' => $task->id(),
                'videoId' => $video->id()->toRfc4122(),
                'exception' => $exception,
            ]);

            throw $exception;
        } finally {
            try {
                $lock->release();
            } catch (\Throwable $exception) {
                $this->logger->error('Failed to release transcode task mutex', [
                    'taskId' => $scheduledTask->taskId,
                    'exception' => $exception,
                ]);
            }
        }

        $this->messageBus->dispatch(new StartTaskScheduler());
    }

    private function runTranscodeProcess(
        string $inputPath,
        string $outputPath,
        Preset $preset,
        ?float $duration,
        Task $task,
    ): array {
        $command = Transcode::buildCommand($inputPath, $outputPath, $preset);
        $process = new Process($command);
        $process->setTimeout(null);

        $buffer = '';
        $ffmpegStats = [];
        $stderrTail = '';
        $stdoutTail = '';
        $lastPersistAt = microtime(true);
        $lastProgressValue = $task->progress()->value();
        $lastCancelCheckAt = 0.0;
        $startedAt = microtime(true);
        $cancelled = false;

        $process->run(function (string $type, string $data) use ($duration, $task, &$buffer, &$lastPersistAt, &$lastProgressValue, &$ffmpegStats, &$stderrTail, &$stdoutTail, &$lastCancelCheckAt, &$cancelled, $process) {
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

        $report = [
            'cancelled' => $cancelled,
            'ffmpeg' => $ffmpegStats,
            'process' => [
                'runtimeSec' => round(microtime(true) - $startedAt, 3),
                'exitCode' => $process->getExitCode(),
                'exitCodeText' => $process->getExitCodeText(),
                'command' => implode(' ', $command),
                'stderrTail' => $stderrTail,
                'stdoutTail' => $stdoutTail,
            ],
        ];

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
        $this->taskRepository->log($task->id(), 'info', 'Transcoding progress: '. $value .'%');
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

