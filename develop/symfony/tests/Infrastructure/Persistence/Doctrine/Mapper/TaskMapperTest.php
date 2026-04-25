<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Video\Entity\Task;
use App\Infrastructure\Persistence\Doctrine\Preset\PresetEntity;
use App\Infrastructure\Persistence\Doctrine\Task\TaskEntity;
use App\Infrastructure\Persistence\Doctrine\Task\TaskMapper;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Persistence\Doctrine\Video\VideoEntity;
use App\Domain\Video\ValueObject\TaskStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

final class TaskMapperTest extends TestCase
{
    private UserEntity $userEntity;
    private VideoEntity $videoEntity;
    private PresetEntity $presetEntity;
    private TaskEntity $taskEntity;

    protected function setUp(): void
    {
        $this->userEntity = new UserEntity();
        $this->userEntity->id = SymfonyUuid::fromString('11111111-1111-4111-8111-111111111111');

        $this->videoEntity = new VideoEntity();
        $this->videoEntity->id = SymfonyUuid::fromString('22222222-2222-4222-8222-222222222222');

        $this->presetEntity = new PresetEntity();
        $this->presetEntity->id = SymfonyUuid::fromString('33333333-3333-4333-8333-333333333333');
        $this->presetEntity->title = 'HD 720p';
        $this->presetEntity->videoCodec = 'h264';
        $this->presetEntity->audioCodec = 'aac';
        $this->presetEntity->format = 'mp4';

        $this->taskEntity = new TaskEntity();
        $this->taskEntity->id = SymfonyUuid::fromString('44444444-4444-4444-8444-444444444444');
        $this->taskEntity->video = $this->videoEntity;
        $this->taskEntity->preset = $this->presetEntity;
        $this->taskEntity->user = $this->userEntity;
        $this->taskEntity->status = TaskStatus::PENDING->value;
        $this->taskEntity->progress = 0;
        $this->taskEntity->createdAt = new DateTimeImmutable('2024-01-01 10:00:00');
        $this->taskEntity->startedAt = null;
        $this->taskEntity->updatedAt = null;
        $this->taskEntity->meta = [];
        $this->taskEntity->deleted = false;
    }

    public function testToDomainMapsAllFields(): void
    {
        $task = TaskMapper::toDomain($this->taskEntity);

        self::assertInstanceOf(Task::class, $task);
        self::assertSame('44444444-4444-4444-8444-444444444444', $task->id()->toRfc4122());
        self::assertSame('22222222-2222-4222-8222-222222222222', $task->videoId()->toRfc4122());
        self::assertSame('33333333-3333-4333-8333-333333333333', $task->presetId()->toRfc4122());
        self::assertSame('11111111-1111-4111-8111-111111111111', $task->userId()->toRfc4122());
        self::assertSame(TaskStatus::PENDING, $task->status());
        self::assertSame(0, $task->progress()->value());
        self::assertFalse($task->isDeleted());
        self::assertSame([], $task->meta());
    }

    public function testToDomainWithProcessingStatus(): void
    {
        $this->taskEntity->status = TaskStatus::PROCESSING->value;
        $this->taskEntity->progress = 55;
        $this->taskEntity->startedAt = new DateTimeImmutable('2024-01-01 10:01:00');

        $task = TaskMapper::toDomain($this->taskEntity);

        self::assertSame(TaskStatus::PROCESSING, $task->status());
        self::assertSame(55, $task->progress()->value());
        self::assertNotNull($task->startedAt());
    }

    public function testToDoctrineCreatesEntityWithCorrectFields(): void
    {
        $task = TaskMapper::toDomain($this->taskEntity);
        $entity = TaskMapper::toDoctrine($task, $this->videoEntity, $this->presetEntity, $this->userEntity);

        self::assertInstanceOf(TaskEntity::class, $entity);
        self::assertSame('44444444-4444-4444-8444-444444444444', $entity->id->toRfc4122());
        self::assertSame(TaskStatus::PENDING->value, $entity->status);
        self::assertSame(0, $entity->progress);
        self::assertSame($this->videoEntity, $entity->video);
        self::assertSame($this->presetEntity, $entity->preset);
        self::assertSame($this->userEntity, $entity->user);
    }

    public function testHydrateUpdatesExistingEntity(): void
    {
        $task = TaskMapper::toDomain($this->taskEntity);
        $target = new TaskEntity();
        TaskMapper::hydrate($target, $task, $this->videoEntity, $this->presetEntity, $this->userEntity);

        self::assertSame(TaskStatus::PENDING->value, $target->status);
        self::assertSame($this->videoEntity, $target->video);
        self::assertSame($this->presetEntity, $target->preset);
    }
}
