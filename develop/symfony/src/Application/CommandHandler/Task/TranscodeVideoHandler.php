<?php

namespace App\Application\CommandHandler\Task;

use App\Application\Command\Task\StartTaskScheduler;
use App\Application\Command\Task\TranscodeVideo;
use App\Application\Service\Ffmpeg\Transcode;
use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\Progress;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

// TODO split and test!
#[AsMessageHandler]
final readonly class TranscodeVideoHandler
{
    private const float PROGRESS_UPDATE_INTERVAL = 5.0;

    public function __construct(
        private MessageBusInterface $messageBus,
        private TaskRepositoryInterface $taskRepository,
        private VideoRepositoryInterface $videoRepository,
        private PresetRepositoryInterface $presetRepository,
        private StorageInterface $storage,
        private Filesystem $filesystem,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function __invoke(TranscodeVideo $command): void
    {
        // TODO check is meta full
        $scheduledTask = $command->scheduledTask;
        $task = $this->taskRepository->findById($scheduledTask->taskId);

        if (!$task) {
            $this->logger->error('Scheduled task not found for transcoding', ['taskId' => $scheduledTask->taskId]);
            return;
        }

        $video = $this->videoRepository->findById($task->videoId());
        if (!$video) {
            $this->taskRepository->log($task->id(), 'error', 'Video not found for transcoding');
            throw new \RuntimeException('Video not found for transcoding');
        }

        $preset = $this->presetRepository->findById($task->presetId());
        if (!$preset) {
            $this->taskRepository->log($task->id(), 'error', 'Preset not found for task');
            throw new \RuntimeException('Preset not found for task');
        }

        // TODO get path from service? / entity?
        $relativeOutputPath = sprintf('%s/%d.mp4', $video->id()->toRfc4122(), $preset->id());
        $absoluteOutputPath = $this->storage->getAbsolutePath($relativeOutputPath);
        $this->filesystem->mkdir(\dirname($absoluteOutputPath));

        try {
            $task->start();
            $this->taskRepository->save($task);
            $this->taskRepository->log($task->id(), 'info', 'Transcoding started');

            $inputPath = $this->storage->getAbsolutePath($video->getSrcFilename());
            $this->runTranscodeProcess($inputPath, $absoluteOutputPath, $preset, $video->duration(), $task);

            $task->updateProgress(new Progress(100));
            $task->updateMeta(['output' => $relativeOutputPath]);
            $this->taskRepository->save($task);
            $this->taskRepository->log($task->id(), 'info', 'Transcoding finished successfully');
        } catch (\Throwable $exception) {
            $task->fail();
            $this->taskRepository->save($task);
            $this->taskRepository->log($task->id(), 'error', 'Transcoding failed: '. $exception->getMessage());
            $this->logger->error('TranscodeVideoHandler failed', [
                'taskId' => $task->id(),
                'videoId' => $video->id()->toRfc4122(),
                'exception' => $exception,
            ]);

            throw $exception;
        }

        $this->messageBus->dispatch(new StartTaskScheduler());
    }

    private function runTranscodeProcess(
        string $inputPath,
        string $outputPath,
        Preset $preset,
        ?float $duration,
        Task $task,
    ): void {
        $command = Transcode::buildCommand($inputPath, $outputPath, $preset);
        $process = new Process($command);
        $process->setTimeout(null);

        $buffer = '';
        $lastPersistAt = microtime(true);
        $lastProgressValue = $task->progress()->value();

        $process->run(function (string $type, string $data) use ($duration, $task, &$buffer, &$lastPersistAt, &$lastProgressValue) {
            if ($type !== Process::OUT && $type !== Process::ERR) {
                return;
            }

            $buffer .= $data;

            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $newlinePos));
                $buffer = substr($buffer, $newlinePos + 1);

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

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
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
}

