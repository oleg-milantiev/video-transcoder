<?php

namespace App\Application\Service\Task;

use App\Application\DTO\TranscodeReportDTO;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Infrastructure\Task\TaskCancellationTrigger;

final readonly class TranscodeTaskFinalizationService
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
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
        $this->taskRepository->log($cancelledTask->id(), 'info', 'Transcoding cancelled');
        $this->cancellationTrigger->clear($cancelledTask->id());
    }

    public function handleSuccess(Task $task, string $relativeOutputPath, TranscodeReportDTO $report): void
    {
        $task->updateProgress(new Progress(100));
        $task->updateMeta([
            'output' => $relativeOutputPath,
            'transcode' => [
                'finishedAt' => new \DateTimeImmutable()->format(DATE_ATOM),
                'report' => $report->toArray(),
            ],
        ]);

        $this->taskRepository->save($task);
        $this->taskRepository->log($task->id(), 'info', 'Transcoding finished successfully');
        $this->cancellationTrigger->clear($task->id());
    }

    public function handleFailure(Task $task, \Throwable $exception): void
    {
        if (!$task->status()->isFinished()) {
            $task->fail();
            $this->taskRepository->save($task);
        }

        $this->taskRepository->log($task->id(), 'error', 'Transcoding failed: '. $exception->getMessage());
    }
}

