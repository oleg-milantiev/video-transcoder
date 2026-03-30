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

final class TaskTest extends TestCase
{
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

    public function testStartSwitchesToProcessingAndSetsDates(): void
    {
        $task = $this->startingTask();

        $task->start(12.5);

        $this->assertSame(TaskStatus::PROCESSING, $task->status());
        $this->assertNotNull($task->startedAt());
        $this->assertNotNull($task->updatedAt());
    }

    public function testStartTwiceThrows(): void
    {
        $task = $this->startingTask();
        $task->start(12.5);

        $this->expectException(\DomainException::class);
        $task->start(12.5);
    }

    public function testUpdateProgressToCompleteMarksTaskCompleted(): void
    {
        $task = $this->startingTask();
        $task->start(12.5);

        $task->updateProgress(new Progress(100));

        $this->assertSame(TaskStatus::COMPLETED, $task->status());
        $this->assertSame(100, $task->progress()->value());
        $this->assertNotNull($task->updatedAt());
    }

    public function testFailMarksTaskFailed(): void
    {
        $task = $this->startingTask();
        $task->start(12.5);

        $task->fail();

        $this->assertSame(TaskStatus::FAILED, $task->status());
    }

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

    public function testCancelCompletedTaskThrows(): void
    {
        $task = $this->startingTask();
        $task->start(12.5);
        $task->updateProgress(new Progress(100));

        $this->expectException(\DomainException::class);
        $task->cancel();
    }

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

    public function testUpdateMetaOverridesSameTopLevelKey(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $task->updateMeta(['output' => 'old.mp4']);

        $task->updateMeta(['output' => 'new.mp4']);

        $this->assertSame('new.mp4', $task->meta()['output']);
    }

    public function testStartWithoutValidDurationThrows(): void
    {
        $task = $this->startingTask();

        $this->expectException(\DomainException::class);
        $task->start(null);
    }

    public function testUpdateProgressBeforeStartThrows(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());

        $this->expectException(\DomainException::class);
        $task->updateProgress(new Progress(1));
    }

    public function testUpdateMetaOnCompletedTaskThrows(): void
    {
        $task = $this->startingTask();
        $task->start(12.5);
        $task->updateProgress(new Progress(100));

        $this->expectException(\DomainException::class);
        $task->updateMeta(['output' => 'completed.mp4']);
    }

    public function testCanStartOnlyForStartingStatus(): void
    {
        $this->assertFalse(Task::create($this->videoId(), $this->presetId(), $this->userId())->canStart(12.5));

        $this->assertTrue($this->startingTask()->canStart(12.5));

        $failedTask = Task::reconstitute(
            $this->videoId(), $this->presetId(), $this->userId(),
            TaskStatus::FAILED, new Progress(0), TaskDates::create(),
            Uuid::fromString('dddddddd-dddd-4ddd-8ddd-dddddddddddd'),
        );
        $this->assertFalse($failedTask->canStart(12.5));

        $cancelledTask = Task::reconstitute(
            $this->videoId(), $this->presetId(), $this->userId(),
            TaskStatus::CANCELLED, new Progress(0), TaskDates::create(),
            Uuid::fromString('dddddddd-dddd-4ddd-8ddd-dddddddddddd'),
        );
        $this->assertFalse($cancelledTask->canStart(12.5));
    }

    public function testMarkDeletedSetsDeletedStatus(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());

        $task->markDeleted();

        $this->assertTrue($task->isDeleted());
        $this->assertSame(TaskStatus::DELETED, $task->status());
    }

    public function testMarkDeletedTwiceThrows(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $task->markDeleted();

        $this->expectException(TaskAlreadyDeleted::class);
        $task->markDeleted();
    }

    public function testCannotUpdateDeletedTask(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $task->markDeleted();

        $this->expectException(TaskAlreadyDeleted::class);
        $task->updateMeta(['x' => 'y']);
    }

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

    public function testCanStartReturnsFalseForDeletedTask(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $task->markDeleted();

        $this->assertFalse($task->canStart(12.5));
    }

    public function testRestartAfterCancelSetsPendingStatus(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $task->cancel();
        $task->restart();

        $this->assertSame(TaskStatus::PENDING, $task->status());
        $this->assertSame(0, $task->progress()->value());
    }

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

    public function testSecondStartAfterRestartUpdatesStartedAt(): void
    {
        $knownStartedAt = new \DateTimeImmutable('2026-03-18 10:05:00');

        // Task already has startedAt from a previous run, now back in STARTING
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
        // startedAt was overwritten with a brand-new DateTimeImmutable — different reference
        $this->assertNotSame($knownStartedAt, $task->startedAt());
    }

    public function testRestartAfterFailSetsPendingStatus(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $task->fail();
        $task->restart();

        $this->assertSame(TaskStatus::PENDING, $task->status());
        $this->assertSame(0, $task->progress()->value());
    }

    public function testRestartOnPendingTaskThrows(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());

        $this->expectException(\DomainException::class);
        $task->restart();
    }

    public function testFailOnFinishedTaskThrows(): void
    {
        $task = $this->startingTask();
        $task->start(12.5);
        $task->updateProgress(new Progress(100));

        $this->expectException(\DomainException::class);
        $task->fail();
    }

    public function testCanBeCancelledReturnsFalseForDeletedTask(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $task->markDeleted();

        $this->assertFalse($task->canBeCancelled());
    }

    public function testAssignIdSetsId(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $id = Uuid::fromString('dddddddd-dddd-4ddd-8ddd-dddddddddddd');

        $task->assignId($id);

        $this->assertSame($id, $task->id());
    }

    public function testAssignSameIdDoesNotThrow(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $id = Uuid::fromString('dddddddd-dddd-4ddd-8ddd-dddddddddddd');

        $task->assignId($id);
        $task->assignId($id);

        $this->assertSame($id, $task->id());
    }

    public function testAssignDifferentIdThrows(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $id1 = Uuid::fromString('dddddddd-dddd-4ddd-8ddd-dddddddddddd');
        $id2 = Uuid::fromString('eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee');

        $task->assignId($id1);

        $this->expectException(\DomainException::class);
        $task->assignId($id2);
    }

    public function testClearOutputSetsOutputToNull(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $task->updateMeta(['output' => 'video.mp4']);

        $task->clearOutput();

        $this->assertNull($task->meta()['output']);
    }

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

