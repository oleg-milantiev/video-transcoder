<?php
declare(strict_types=1);

namespace App\Application\Service\Task;

use App\Application\DTO\TranscodeReportDTO;
use App\Application\DTO\TranscodeStartContextDTO;
use App\Application\Factory\FlashNotificationFactory;
use Psr\Log\LogLevel;
use App\Application\Logging\LogServiceInterface;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Infrastructure\Task\TaskCancellationTrigger;

readonly class TranscodeTaskFinalizationService
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private LogServiceInterface $logService,
        private TaskRealtimeNotifier $taskRealtimeNotifier,
        private FlashNotificationFactory $flashNotificationFactory,
        private TaskCancellationTrigger $cancellationTrigger,
    ) {
    }

    public function handleCancellation(Task $task, TranscodeReportDTO $report): void
    {
        $cancelledTask = $this->taskRepository->findByIdFresh($task->id()) ?? $task;
        if ($cancelledTask->status() !== TaskStatus::CANCELLED) {
            $cancelledTask->cancel();
        }

        $cancelledTask->updateMeta([
            'transcode' => [
                'cancelledAt' => new \DateTimeImmutable()->format(DATE_ATOM),
                'report' => $report->toArray(),
            ],
        ]);

        $this->taskRepository->save($cancelledTask);
        $this->logService->log('task', $cancelledTask->id(), LogLevel::INFO, 'Transcoding cancelled');
        $this->taskRealtimeNotifier->notifyTaskUpdated($cancelledTask, 'cancelled');
        $this->cancellationTrigger->clear($cancelledTask->id());
    }

    public function handleSuccess(Task $task, TranscodeStartContextDTO $context, TranscodeReportDTO $report): void
    {
        $task->updateMeta([
            'size' => filesize($context->absoluteOutputPath),
            'output' => $context->relativeOutputPath,
            'transcode' => [
                'finishedAt' => new \DateTimeImmutable()->format(DATE_ATOM),
                'report' => $report->toArray(),
            ],
        ]);
        $task->updateProgress(new Progress(100));

        $this->taskRepository->save($task);
        $this->logService->log('task', $task->id(), LogLevel::INFO, 'Transcoding finished successfully');
        $this->taskRealtimeNotifier->notifyTaskUpdated($task, 'completed', [
            'notification' => $this->flashNotificationFactory->transcodeCompleted($task)->toArray(),
        ]);
        $this->cancellationTrigger->clear($task->id());
    }

    public function handleFailure(Task $task, \Throwable $exception, TranscodeStartContextDTO $context): void
    {
        if (!$task->status()->isFinished()) {
            $task->fail();
            $this->taskRepository->save($task);
            $this->taskRealtimeNotifier->notifyTaskUpdated($task, 'failed', [
                'error' => $exception->getMessage(),
                'notification' => $this->flashNotificationFactory->transcodeFailed($task, $exception)->toArray(),
            ]);
        }

        if (file_exists($context->absoluteOutputPath)) {
            unlink($context->absoluteOutputPath);
        }

        $this->logService->log('task', $task->id(), LogLevel::ERROR, 'Transcoding failed', [
            'message' => $exception->getMessage(),
        ]);
    }
}

