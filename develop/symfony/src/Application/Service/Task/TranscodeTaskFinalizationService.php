<?php
declare(strict_types=1);

namespace App\Application\Service\Task;

use App\Application\DTO\TranscodeReportDTO;
use App\Application\DTO\TranscodeStartContextDTO;
use App\Application\Factory\FlashNotificationFactory;
use App\Application\Logging\LogServiceInterface;
use App\Application\Service\Storage\StorageRealtimeNotifier;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Infrastructure\Task\TaskCancellationTrigger;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

readonly class TranscodeTaskFinalizationService
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private LogServiceInterface $logService,
        private TaskRealtimeNotifier $taskRealtimeNotifier,
        private FlashNotificationFactory $flashNotificationFactory,
        private TaskCancellationTrigger $cancellationTrigger,
        private StorageRealtimeNotifier $storageNotifier,
        private Filesystem $filesystem,
    ) {
    }

    public function handleCancellation(TranscodeStartContextDTO $context, TranscodeReportDTO $report): void
    {
        $cancelledTask = $this->taskRepository->findByIdFresh($context->task->id()) ?? $context->task;
        if ($cancelledTask->status() !== TaskStatus::CANCELLED) {
            $cancelledTask->cancel();
        }

        $cancelledTask->clearSizeExpected();
        $cancelledTask->updateMeta([
            'transcode' => [
                'cancelledAt' => new DateTimeImmutable()->format(DateTimeInterface::ATOM),
                'report' => $report->toArray(),
            ],
        ]);

        $this->taskRepository->save($cancelledTask);
        $this->removeOutputFile($context->absoluteOutputPath, $cancelledTask->id(), 'cancel');
        $this->logService->log('task', 'cancel', $cancelledTask->id(), LogLevel::INFO, 'Transcoding cancelled');
        $this->taskRealtimeNotifier->notifyTaskUpdated($cancelledTask, 'cancelled');
        $this->cancellationTrigger->clear($cancelledTask->id());
        $this->storageNotifier->notifyStorageUpdated($cancelledTask->userId());
    }

    private function removeOutputFile(?string $path, ?Uuid $taskId, string $context): void
    {
        try {
            if (!empty($path) && $this->filesystem->exists($path)) {
                $this->filesystem->remove($path);
            }
        } catch (Throwable $e) {
            $this->logService->log('task', $context, $taskId, LogLevel::WARNING, 'Failed to remove output file', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handleSuccess(TranscodeStartContextDTO $context, TranscodeReportDTO $report): void
    {
        $task = $context->task;
        $fileSize = filesize($context->absoluteOutputPath);
        $task->updateMeta([
            'size' => $fileSize,
            'output' => $context->relativeOutputPath,
            'transcode' => [
                'finishedAt' => new DateTimeImmutable()->format(DateTimeInterface::ATOM),
                'report' => $report->toArray(),
            ],
        ]);
        $task->updateProgress(new Progress(100));

        $this->taskRepository->save($task);
        $this->taskRealtimeNotifier->notifyTaskUpdated($task, 'completed', [
            'notification' => $this->flashNotificationFactory->transcodeCompleted($task)->toArray(),
        ]);
        $this->cancellationTrigger->clear($task->id());
        $this->storageNotifier->notifyStorageUpdated($task->userId());
        $this->logService->log('task', 'transcode', $task->id(), LogLevel::INFO, 'Transcoding finished successfully', [
            'videoId' => $task->videoId()?->toRfc4122(),
            'presetId' => $task->presetId()?->toRfc4122(),
            'height' => $task->heightNullable(),
            'userId' => $task->userId()?->toRfc4122(),
            'time' => microtime(true) - $context->timeStart,
            'size' => $fileSize,
        ]);
    }

    public function handleFailure(Task $task, Throwable $exception, ?string $absoluteOutputPath): void
    {
        if (!$task->status()->isFinished()) {
            $task->fail();
            $this->taskRepository->save($task);
            $this->taskRealtimeNotifier->notifyTaskUpdated($task, 'failed', [
                'error' => $exception->getMessage(),
                'notification' => $this->flashNotificationFactory->transcodeFailed($task, $exception)->toArray(),
            ]);
        }

        $this->removeOutputFile($absoluteOutputPath, $task->id(), 'transcode');

        $this->logService->log('task', 'transcode', $task->id(), LogLevel::ERROR, 'Transcoding failed', [
            'message' => $exception->getMessage(),
        ]);
    }
}
