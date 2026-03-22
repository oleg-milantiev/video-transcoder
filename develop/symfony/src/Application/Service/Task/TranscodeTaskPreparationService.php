<?php

namespace App\Application\Service\Task;

use App\Application\DTO\TranscodeStartContextDTO;
use Psr\Log\LogLevel;
use App\Application\Logging\LogServiceInterface;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use Symfony\Component\Filesystem\Filesystem;

final readonly class TranscodeTaskPreparationService
{
    public function __construct(
        private PresetRepositoryInterface $presetRepository,
        private TaskRepositoryInterface $taskRepository,
        private LogServiceInterface $logService,
        private StorageInterface $storage,
        private Filesystem $filesystem,
    ) {
    }

    public function prepare(Task $task, Video $video): TranscodeStartContextDTO
    {
        $preset = $this->presetRepository->findById($task->presetId());
        if (!$preset) {
            $this->logService->log('task', $task->id(), LogLevel::ERROR, 'Preset not found for task');
            throw new \RuntimeException('Preset not found for task');
        }

        // TODO use abstract storage
        $relativeOutputPath = sprintf('%s/%s.mp4', $video->id()->toRfc4122(), $preset->id()->toRfc4122());
        $absoluteOutputPath = $this->storage->getAbsolutePath($relativeOutputPath);
        $this->filesystem->mkdir(\dirname($absoluteOutputPath));

        $task->start();
        $this->taskRepository->save($task);
        $this->logService->log('task', $task->id(), LogLevel::INFO, 'Transcoding started');

        $inputPath = $this->storage->getAbsolutePath($video->getSrcFilename());

        return new TranscodeStartContextDTO(
            task: $task,
            video: $video,
            preset: $preset,
            relativeOutputPath: $relativeOutputPath,
            absoluteOutputPath: $absoluteOutputPath,
            inputPath: $inputPath,
        );
    }
}

