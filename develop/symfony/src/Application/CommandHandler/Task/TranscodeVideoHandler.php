<?php

namespace App\Application\CommandHandler\Task;

use App\Application\Command\Task\TranscodeVideo;
use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\Bitrate;
use App\Domain\Video\ValueObject\Codec;
use App\Domain\Video\ValueObject\Progress;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

#[AsMessageHandler]
final readonly class TranscodeVideoHandler
{
    private const PROGRESS_UPDATE_INTERVAL = 5.0;

    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private VideoRepositoryInterface $videoRepository,
        private PresetRepositoryInterface $presetRepository,
        private StorageInterface $storage,
        private Filesystem $filesystem,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(TranscodeVideo $command): void
    {
        $scheduledTask = $command->scheduledTask;
        $task = $this->taskRepository->findById($scheduledTask->taskId);

        if (!$task) {
            $this->logger->error('Scheduled task not found for transcoding', ['taskId' => $scheduledTask->taskId]);
            return;
        }

        $taskId = $task->id();
        if ($taskId === null) {
            throw new \RuntimeException('Task does not have a persistent identifier');
        }

        $video = $this->videoRepository->findById($scheduledTask->videoId);
        if (!$video) {
            $this->taskRepository->log($taskId, 'error', 'Video not found for transcoding');
            throw new \RuntimeException('Video not found for transcoding');
        }

        $preset = $this->presetRepository->findById($task->presetId());
        if (!$preset) {
            $this->taskRepository->log($taskId, 'error', 'Preset not found for task');
            throw new \RuntimeException('Preset not found for task');
        }

        $presetId = $preset->id();
        if ($presetId === null) {
            $this->taskRepository->log($taskId, 'error', 'Preset does not have a persistent identifier');
            throw new \RuntimeException('Preset does not have a persistent identifier');
        }

        $videoId = $video->id();
        if (!$videoId) {
            $this->taskRepository->log($taskId, 'error', 'Video does not have an identifier yet');
            throw new \RuntimeException('Video does not have an identifier yet');
        }

        $relativeOutputPath = sprintf('uploads/%s/%d.mp4', $videoId->toString(), $presetId);
        $absoluteOutputPath = $this->storage->getAbsolutePath($relativeOutputPath);
        $this->filesystem->mkdir(\dirname($absoluteOutputPath));

        try {
            $task->start();
            $this->taskRepository->save($task);
            $this->taskRepository->log($taskId, 'info', 'Transcoding started');

            $inputPath = $this->storage->getAbsolutePath($video->getSrcFilename());
            $this->runTranscodeProcess($inputPath, $absoluteOutputPath, $preset, $video->duration(), $task);

            $task->updateProgress(new Progress(100));
            $task->updateMeta(['output' => $relativeOutputPath]);
            $this->taskRepository->save($task);
            $this->taskRepository->log($taskId, 'info', 'Transcoding finished successfully');
        } catch (\Throwable $exception) {
            $task->fail();
            $this->taskRepository->save($task);
            $this->taskRepository->log($taskId, 'error', 'Transcoding failed: '. $exception->getMessage());
            $this->logger->error('TranscodeVideoHandler failed', [
                'taskId' => $taskId,
                'videoId' => $videoId->toString(),
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }

    private function runTranscodeProcess(
        string $inputPath,
        string $outputPath,
        Preset $preset,
        ?float $duration,
        Task $task,
    ): void {
        $command = $this->buildCommand($inputPath, $outputPath, $preset);
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

    private function buildCommand(string $inputPath, string $outputPath, Preset $preset): array
    {
        $resolution = $preset->resolution();
        $codec = $preset->codec();
        $bitrate = $preset->bitrate();

        return [
            'ffmpeg',
            '-y',
            '-i', $inputPath,
            '-vf', sprintf('scale=%d:%d', $resolution->width(), $resolution->height()),
            '-c:v', $this->mapCodec($codec),
            '-b:v', $this->formatBitrate($bitrate),
            '-preset', 'medium',
            '-movflags', '+faststart',
            '-c:a', 'aac',
            '-b:a', '128k',
            '-progress', 'pipe:2',
            '-nostats',
            $outputPath,
        ];
    }

    private function mapCodec(Codec $codec): string
    {
        return match ($codec->value()) {
            'h265' => 'libx265',
            'vp9' => 'libvpx-vp9',
            'av1' => 'libaom-av1',
            default => 'libx264',
        };
    }

    private function formatBitrate(Bitrate $bitrate): string
    {
        $kbps = max(100, (int) round($bitrate->value() * 1000));
        return $kbps . 'k';
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
        $taskId = $task->id();
        if ($taskId !== null) {
            $this->taskRepository->log($taskId, 'info', 'Transcoding progress: '. $value .'%');
        }
    }
}

