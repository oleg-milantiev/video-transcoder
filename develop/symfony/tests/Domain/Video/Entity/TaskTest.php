<?php

declare(strict_types=1);

namespace App\Tests\Domain\Video\Entity;

use App\Domain\Video\Entity\Task;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV4;

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
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());

        $this->assertTrue($task->canStart(12.5));
        $this->assertFalse($task->canStart(null));
        $this->assertFalse($task->canStart(0.0));
        $this->assertFalse($task->canStart(-1.0));

        $task->start();

        $this->assertFalse($task->canStart(12.5));
    }

    public function testStartSwitchesToProcessingAndSetsDates(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());

        $task->start();

        $this->assertSame(TaskStatus::PROCESSING, $task->status());
        $this->assertNotNull($task->startedAt());
        $this->assertNotNull($task->updatedAt());
    }

    public function testStartTwiceThrows(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $task->start();

        $this->expectException(\DomainException::class);
        $task->start();
    }

    public function testUpdateProgressToCompleteMarksTaskCompleted(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $task->start();

        $task->updateProgress(new Progress(100));

        $this->assertSame(TaskStatus::COMPLETED, $task->status());
        $this->assertSame(100, $task->progress()->value());
        $this->assertNotNull($task->updatedAt());
    }

    public function testFailMarksTaskFailed(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $task->start();

        $task->fail();

        $this->assertSame(TaskStatus::FAILED, $task->status());
    }

    public function testCancelMarksTaskCancelledForPendingAndProcessing(): void
    {
        $pendingTask = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $pendingTask->cancel();
        $this->assertSame(TaskStatus::CANCELLED, $pendingTask->status());

        $processingTask = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $processingTask->start();
        $processingTask->cancel();
        $this->assertSame(TaskStatus::CANCELLED, $processingTask->status());
    }

    public function testCancelCompletedTaskThrows(): void
    {
        $task = Task::create($this->videoId(), $this->presetId(), $this->userId());
        $task->start();
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

    private function videoId(): UuidV4
    {
        return UuidV4::fromString('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
    }

    private function presetId(): UuidV4
    {
        return UuidV4::fromString('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb');
    }

    private function userId(): UuidV4
    {
        return UuidV4::fromString('cccccccc-cccc-4ccc-8ccc-cccccccccccc');
    }
}

