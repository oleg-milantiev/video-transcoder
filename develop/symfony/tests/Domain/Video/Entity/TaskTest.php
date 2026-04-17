<?php

declare(strict_types=1);

namespace App\Tests\Domain\Video\Entity;

use App\Domain\Video\Entity\Task;
use App\Domain\Video\Exception\TaskAlreadyDeleted;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskDates;
use App\Domain\Video\ValueObject\TaskStatus;
use PHPUnit\Framework\TestCase;
use App\Domain\Shared\ValueObject\Uuid;

/**
 * Tests Task entity — полный жизненный цикл задачи: create/reconstitute, start, updateProgress,
 * fail, cancel, restart, markDeleted, updateMeta, assignId, clearOutput/clearSizeExpected.
 */
final class TaskTest extends TestCase
{
    /** create() создаёт задачу в статусе PENDING без id и с пустой meta. */
    public function testCreateInitializesPendingTaskWithDefaults(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());

        $this->assertNull($task->id());
        $this->assertSame(TaskStatus::PENDING, $task->status());
        $this->assertSame(0, $task->progress()->value());
        $this->assertSame([], $task->meta());
        $this->assertNull($task->startedAt());
        $this->assertNull($task->updatedAt());
    }

    /** canStart() требует статус STARTING и положительную длительность видео. */
    public function testCanStartDependsOnStatusAndDuration(): void
    {
        $task = $this->startingTask();

        $this->assertTrue($task->canStart(12.5));
        $this->assertFalse($task->canStart(null));
        $this->assertFalse($task->canStart(0.0));
        $this->assertFalse($task->canStart(-1.0));

        $task->start(12.5);

        $this->assertFalse($task->canStart(12.5));
    }

    /** start() переводит задачу в PROCESSING и заполняет startedAt/updatedAt. */
    public function testStartSwitchesToProcessingAndSetsDates(): void
    {
        $task = $this->startingTask();

        $task->start(12.5);

        $this->assertSame(TaskStatus::PROCESSING, $task->status());
        $this->assertNotNull($task->startedAt());
        $this->assertNotNull($task->updatedAt());
    }

    /** Повторный вызов start() на уже запущенной задаче бросает DomainException. */
    public function testStartTwiceThrows(): void
    {
        $task = $this->startingTask();
        $task->start(12.5);

        $this->expectException(\DomainException::class);
        $task->start(12.5);
    }

    /** updateProgress(100) переводит задачу в COMPLETED и обновляет updatedAt. */
    public function testUpdateProgressToCompleteMarksTaskCompleted(): void
    {
        $task = $this->startingTask();
        $task->start(12.5);

        $task->updateProgress(new Progress(100));

        $this->assertSame(TaskStatus::COMPLETED, $task->status());
        $this->assertSame(100, $task->progress()->value());
        $this->assertNotNull($task->updatedAt());
    }

    /** fail() переводит задачу в FAILED. */
    public function testFailMarksTaskFailed(): void
    {
        $task = $this->startingTask();
        $task->start(12.5);

        $task->fail();

        $this->assertSame(TaskStatus::FAILED, $task->status());
    }

    /** fail() работает в статусах PENDING и STARTING (не только PROCESSING). */
    public function testFailOnPendingAndStartingTaskMarksFailed(): void
    {
        $pending = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $pending->fail();
        $this->assertSame(TaskStatus::FAILED, $pending->status());

        $starting = $this->startingTask();
        $starting->fail();
        $this->assertSame(TaskStatus::FAILED, $starting->status());
    }

    /** cancel() переводит задачу в CANCELLED из статусов PENDING и PROCESSING. */
    public function testCancelMarksTaskCancelledForPendingAndProcessing(): void
    {
        $pendingTask = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $pendingTask->cancel();
        $this->assertSame(TaskStatus::CANCELLED, $pendingTask->status());

        $processingTask = $this->startingTask();
        $processingTask->start(12.5);
        $processingTask->cancel();
        $this->assertSame(TaskStatus::CANCELLED, $processingTask->status());
    }

    /** cancel() на завершённой (COMPLETED) задаче бросает DomainException. */
    public function testCancelCompletedTaskThrows(): void
    {
        $task = $this->startingTask();
        $task->start(12.5);
        $task->updateProgress(new Progress(100));

        $this->expectException(\DomainException::class);
        $task->cancel();
    }

    /** updateMeta() объединяет новые ключи с существующими и обновляет updatedAt. */
    public function testUpdateMetaAddsNewKeysAndKeepsExistingOnes(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $task->updateMeta([
            'output' => 'old.mp4',
            'transcode' => ['runtimeSec' => 12.3],
        ]);

        $task->updateMeta(['cancelRequestedAt' => '2026-03-19T10:00:00+00:00']);
        $task->updateMeta(['cancelledByUserId' => 42]);

        $this->assertSame('old.mp4', $task->meta()['output']);
        $this->assertSame(['runtimeSec' => 12.3], $task->meta()['transcode']);
        $this->assertSame('2026-03-19T10:00:00+00:00', $task->meta()['cancelRequestedAt']);
        $this->assertSame(42, $task->meta()['cancelledByUserId']);
        $this->assertNotNull($task->updatedAt());
    }

    /** updateMeta() перезаписывает существующий ключ. */
    public function testUpdateMetaOverridesSameTopLevelKey(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $task->updateMeta(['output' => 'old.mp4']);

        $task->updateMeta(['output' => 'new.mp4']);

        $this->assertSame('new.mp4', $task->meta()['output']);
    }

    /** start() с null или нулевой длительностью бросает DomainException. */
    public function testStartWithoutValidDurationThrows(): void
    {
        $task = $this->startingTask();

        $this->expectException(\DomainException::class);
        $task->start(null);
    }

    /** updateProgress() в статусе, отличном от PROCESSING, бросает DomainException. */
    public function testUpdateProgressBeforeStartThrows(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());

        $this->expectException(\DomainException::class);
        $task->updateProgress(new Progress(1));
    }

    /** updateMeta() на завершённой задаче (COMPLETED) бросает DomainException. */
    public function testUpdateMetaOnCompletedTaskThrows(): void
    {
        $task = $this->startingTask();
        $task->start(12.5);
        $task->updateProgress(new Progress(100));

        $this->expectException(\DomainException::class);
        $task->updateMeta(['output' => 'completed.mp4']);
    }

    /** canStart() возвращает true только для статуса STARTING. */
    public function testCanStartOnlyForStartingStatus(): void
    {
        $this->assertFalse(Task::create($this->videoId(), $this->presetId(), $this->userId())->canStart(12.5));
        $this->assertTrue($this->startingTask()->canStart(12.5));

        foreach ([TaskStatus::FAILED, TaskStatus::CANCELLED] as $status) {
            $task = Task::reconstitute(
                $this->videoId(), $this->presetId(), $this->userId(),
                $status, new Progress(0), TaskDates::create(),
                Uuid::fromString('dddddddd-dddd-4ddd-8ddd-dddddddddddd'),
            );
            $this->assertFalse($task->canStart(12.5));
        }
    }

    /** markDeleted() переводит задачу в статус DELETED и isDeleted() == true. */
    public function testMarkDeletedSetsDeletedStatus(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());

        $task->markDeleted();

        $this->assertTrue($task->isDeleted());
        $this->assertSame(TaskStatus::DELETED, $task->status());
    }

    /** Повторный markDeleted() бросает TaskAlreadyDeleted. */
    public function testMarkDeletedTwiceThrows(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $task->markDeleted();

        $this->expectException(TaskAlreadyDeleted::class);
        $task->markDeleted();
    }

    /** Любая мутирующая операция на удалённой задаче бросает TaskAlreadyDeleted. */
    public function testCannotUpdateDeletedTask(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $task->markDeleted();

        $this->expectException(TaskAlreadyDeleted::class);
        $task->updateMeta(['x' => 'y']);
    }

    /** reconstitute() восстанавливает все поля из персистентного слоя. */
    public function testReconstituteSetsAllFields(): void
    {
        $id = Uuid::fromString('dddddddd-dddd-4ddd-8ddd-dddddddddddd');
        $createdAt = new \DateTimeImmutable('2026-03-18 10:00:00');

        $task = Task::reconstitute(
            videoId: $this->videoId(),
            presetId: $this->presetId(),
            userId: $this->userId(),
            status: TaskStatus::PROCESSING,
            progress: new Progress(50),
            dates: TaskDates::create($createdAt),
            id: $id,
            meta: ['output' => 'test.mp4'],
        );

        $this->assertSame($id, $task->id());
        $this->assertSame(TaskStatus::PROCESSING, $task->status());
        $this->assertSame(50, $task->progress()->value());
        $this->assertSame('test.mp4', $task->meta()['output']);
        $this->assertSame($createdAt, $task->createdAt());
        $this->assertSame($this->videoId()->toRfc4122(), $task->videoId()->toRfc4122());
        $this->assertSame($this->presetId()->toRfc4122(), $task->presetId()->toRfc4122());
        $this->assertSame($this->userId()->toRfc4122(), $task->userId()->toRfc4122());
    }

    /** reconstitute() с TaskStatus::DELETED устанавливает isDeleted() == true. */
    public function testReconstitutedTaskWithDeletedStatusIsDeleted(): void
    {
        $task = Task::reconstitute(
            videoId: $this->videoId(),
            presetId: $this->presetId(),
            userId: $this->userId(),
            status: TaskStatus::DELETED,
            progress: new Progress(0),
            dates: TaskDates::create(),
            id: Uuid::fromString('dddddddd-dddd-4ddd-8ddd-dddddddddddd'),
            meta: [],
            deleted: false,
        );

        $this->assertTrue($task->isDeleted());
    }

    /** canStart() возвращает false для удалённой задачи. */
    public function testCanStartReturnsFalseForDeletedTask(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $task->markDeleted();

        $this->assertFalse($task->canStart(12.5));
    }

    /** restart() после cancel() переводит задачу обратно в PENDING с нулевым прогрессом. */
    public function testRestartAfterCancelSetsPendingStatus(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $task->cancel();
        $task->restart();

        $this->assertSame(TaskStatus::PENDING, $task->status());
        $this->assertSame(0, $task->progress()->value());
    }

    /** restart() сохраняет startedAt предыдущего запуска (не обнуляет). */
    public function testRestartPreservesStartedAt(): void
    {
        $task = $this->startingTask();
        $task->start(12.5);
        $firstStartedAt = $task->startedAt();
        $this->assertNotNull($firstStartedAt);

        $task->fail();
        $task->restart();

        $this->assertSame(TaskStatus::PENDING, $task->status());
        $this->assertSame($firstStartedAt, $task->startedAt(), 'startedAt must never be erased');
    }

    /** Повторный start() после restart() обновляет startedAt на новое значение. */
    public function testSecondStartAfterRestartUpdatesStartedAt(): void
    {
        $knownStartedAt = new \DateTimeImmutable('2026-03-18 10:05:00');

        $task = Task::reconstitute(
            videoId: $this->videoId(),
            presetId: $this->presetId(),
            userId: $this->userId(),
            status: TaskStatus::STARTING,
            progress: new Progress(0),
            dates: TaskDates::fromPersistence(
                new \DateTimeImmutable('2026-03-18 10:00:00'),
                $knownStartedAt,
                new \DateTimeImmutable('2026-03-18 10:06:00'),
            ),
            id: Uuid::fromString('dddddddd-dddd-4ddd-8ddd-dddddddddddd'),
        );

        $task->start(12.5);

        $this->assertSame(TaskStatus::PROCESSING, $task->status());
        $this->assertNotNull($task->startedAt());
        $this->assertNotSame($knownStartedAt, $task->startedAt());
    }

    /** restart() после fail() переводит задачу в PENDING с нулевым прогрессом. */
    public function testRestartAfterFailSetsPendingStatus(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $task->fail();
        $task->restart();

        $this->assertSame(TaskStatus::PENDING, $task->status());
        $this->assertSame(0, $task->progress()->value());
    }

    /** restart() в статусе PENDING бросает DomainException. */
    public function testRestartOnPendingTaskThrows(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());

        $this->expectException(\DomainException::class);
        $task->restart();
    }

    /** fail() на завершённой (COMPLETED) задаче бросает DomainException. */
    public function testFailOnFinishedTaskThrows(): void
    {
        $task = $this->startingTask();
        $task->start(12.5);
        $task->updateProgress(new Progress(100));

        $this->expectException(\DomainException::class);
        $task->fail();
    }

    /** canBeCancelled() возвращает false для удалённой задачи. */
    public function testCanBeCancelledReturnsFalseForDeletedTask(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $task->markDeleted();

        $this->assertFalse($task->canBeCancelled());
    }

    /** assignId() устанавливает id на новую задачу. */
    public function testAssignIdSetsId(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $id = Uuid::fromString('dddddddd-dddd-4ddd-8ddd-dddddddddddd');

        $task->assignId($id);

        $this->assertSame($id, $task->id());
    }

    /** Повторный assignId() с тем же id не бросает исключение. */
    public function testAssignSameIdDoesNotThrow(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $id = Uuid::fromString('dddddddd-dddd-4ddd-8ddd-dddddddddddd');

        $task->assignId($id);
        $task->assignId($id);

        $this->assertSame($id, $task->id());
    }

    /** assignId() с другим id бросает DomainException. */
    public function testAssignDifferentIdThrows(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $id1 = Uuid::fromString('dddddddd-dddd-4ddd-8ddd-dddddddddddd');
        $id2 = Uuid::fromString('eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee');

        $task->assignId($id1);

        $this->expectException(\DomainException::class);
        $task->assignId($id2);
    }

    /** clearOutput() устанавливает meta['output'] в null. */
    public function testClearOutputSetsOutputToNull(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $task->updateMeta(['output' => 'video.mp4']);

        $task->clearOutput();

        $this->assertNull($task->meta()['output']);
    }

    /** clearSizeExpected() удаляет ключ sizeExpected из meta, не затрагивая остальные. */
    public function testClearSizeExpectedRemovesKey(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $task->updateMeta(['sizeExpected' => 1024000, 'output' => 'file.mp4']);

        $task->clearSizeExpected();

        $this->assertArrayNotHasKey('sizeExpected', $task->meta());
        $this->assertSame('file.mp4', $task->meta()['output']);
        $this->assertNotNull($task->updatedAt());
    }

    /** canBeCancelled() возвращает true для статуса STARTING. */
    public function testCanBeCancelledReturnsTrueForStartingStatus(): void
    {
        $task = $this->startingTask(); // status = STARTING

        $this->assertTrue($task->canBeCancelled());
    }

    /** cancel() успешно отменяет задачу в статусе STARTING. */
    public function testCancelStartingTaskSucceeds(): void
    {
        $task = $this->startingTask();

        $task->cancel();

        $this->assertSame(TaskStatus::CANCELLED, $task->status());
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function startingTask(): Task
    {
        return Task::reconstitute(
            videoId: $this->videoId(),
            presetId: $this->presetId(),
            userId: $this->userId(),
            status: TaskStatus::STARTING,
            progress: new Progress(0),
            dates: TaskDates::create(),
            id: Uuid::fromString('dddddddd-dddd-4ddd-8ddd-dddddddddddd'),
        );
    }

    private function videoId(): Uuid
    {
        return Uuid::fromString('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
    }

    private function presetId(): Uuid
    {
        return Uuid::fromString('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb');
    }

    private function userId(): Uuid
    {
        return Uuid::fromString('cccccccc-cccc-4ccc-8ccc-cccccccccccc');
    }
}

