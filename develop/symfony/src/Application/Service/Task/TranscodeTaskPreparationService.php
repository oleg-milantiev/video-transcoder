<?php
declare(strict_types=1);

namespace App\Application\Service\Task;

use App\Application\DTO\TranscodeStartContextDTO;
use App\Application\Factory\FlashNotificationFactory;
use Psr\Log\LogLevel;
use App\Application\Logging\LogServiceInterface;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;

readonly class TranscodeTaskPreparationService
{
    public function __construct(
        private PresetRepositoryInterface $presetRepository,
        private TaskRepositoryInterface $taskRepository,
        private LogServiceInterface $logService,
        private TaskRealtimeNotifier $taskRealtimeNotifier,
        private FlashNotificationFactory $flashNotificationFactory,
        private StorageInterface $storage,
    ) {
    }

    public function prepare(Task $task, Video $video): TranscodeStartContextDTO
    {
        $preset = $this->presetRepository->findById($task->presetId());
        if (!$preset) {
            $this->logService->log('task', 'transcode', $task->id(), LogLevel::ERROR, 'Preset not found for task');
            throw new \RuntimeException('Preset not found for task');
        }

        $relativeOutputPath = $this->storage->taskOutputKey($video, $preset);
        $absoluteOutputPath = $this->storage->localPathForWrite($relativeOutputPath);

        $task->start($video->duration());
        $this->taskRepository->save($task);
        $this->logService->log('task', 'transcode', $task->id(), LogLevel::INFO, 'Transcoding started');
        $this->taskRealtimeNotifier->notifyTaskUpdated($task, 'started', [
            'notification' => $this->flashNotificationFactory->transcodeStarted($task)->toArray(),
        ]);

        $inputPath = $this->storage->localPathForRead($this->storage->sourceKey($video));

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

