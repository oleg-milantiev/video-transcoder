<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Task;

use App\Application\DTO\TranscodeProcessReportDTO;
use App\Application\DTO\TranscodeReportDTO;
use App\Application\Service\Task\TranscodeTaskFinalizationService;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Infrastructure\Task\TaskCancellationTrigger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Uid\UuidV4;

class TranscodeTaskFinalizationServiceTest extends TestCase
{
    public function testHandleCancellationUsesFreshTaskAndPersistsCancelledReport(): void
    {
        $originalTask = $this->createTask(10);
        $freshTask = $this->createTask(10);
        $report = $this->createReport(true);

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())
            ->method('findByIdFresh')
            ->with(10)
            ->willReturn($freshTask);

        $taskRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Task $task) use ($report): bool {
                $meta = $task->meta();

                return $task->status() === TaskStatus::CANCELLED
                    && isset($meta['transcode']['cancelledAt'])
                    && ($meta['transcode']['report'] ?? null) === $report->toArray();
            }));

        $taskRepository->expects($this->once())
            ->method('log')
            ->with(10, 'info', 'Transcoding cancelled');

        $cancellationTrigger = new TaskCancellationTrigger(new ArrayAdapter());
        $cancellationTrigger->request(10);

        $service = new TranscodeTaskFinalizationService($taskRepository, $cancellationTrigger);
        $service->handleCancellation($originalTask, $report);

        $this->assertFalse($cancellationTrigger->isRequested(10));
    }

    public function testHandleSuccessStoresOutputAndCompletesTask(): void
    {
        $task = $this->createTask(11);
        $task->start();
        $report = $this->createReport(false);

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Task $savedTask) use ($report): bool {
                $meta = $savedTask->meta();

                return $savedTask->status() === TaskStatus::COMPLETED
                    && $savedTask->progress()->value() === 100
                    && ($meta['output'] ?? null) === 'video/11.mp4'
                    && isset($meta['transcode']['finishedAt'])
                    && ($meta['transcode']['report'] ?? null) === $report->toArray();
            }));

        $taskRepository->expects($this->once())
            ->method('log')
            ->with(11, 'info', 'Transcoding finished successfully');

        $cancellationTrigger = new TaskCancellationTrigger(new ArrayAdapter());
        $cancellationTrigger->request(11);

        $service = new TranscodeTaskFinalizationService($taskRepository, $cancellationTrigger);
        $service->handleSuccess($task, 'video/11.mp4', $report);

        $this->assertFalse($cancellationTrigger->isRequested(11));
    }

    public function testHandleFailureFailsActiveTaskAndLogsError(): void
    {
        $task = $this->createTask(12);

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(static fn (Task $savedTask): bool => $savedTask->status() === TaskStatus::FAILED));

        $taskRepository->expects($this->once())
            ->method('log')
            ->with(12, 'error', 'Transcoding failed: boom');

        $service = new TranscodeTaskFinalizationService($taskRepository, new TaskCancellationTrigger(new ArrayAdapter()));
        $service->handleFailure($task, new \RuntimeException('boom'));
    }

    private function createTask(int $id): Task
    {
        $task = Task::create(UuidV4::fromString('123e4567-e89b-42d3-a456-426614174199'), 1, 7);
        $task->setId($id);

        return $task;
    }

    private function createReport(bool $cancelled): TranscodeReportDTO
    {
        return new TranscodeReportDTO(
            cancelled: $cancelled,
            ffmpeg: [
                'progress' => $cancelled ? 'cancelled' : 'end',
            ],
            process: new TranscodeProcessReportDTO(
                runtimeSec: 1.234,
                exitCode: $cancelled ? 255 : 0,
                exitCodeText: $cancelled ? 'SIGTERM' : 'OK',
                command: 'ffmpeg -i in out',
                stderrTail: 'stderr',
                stdoutTail: 'stdout',
            ),
        );
    }
}



