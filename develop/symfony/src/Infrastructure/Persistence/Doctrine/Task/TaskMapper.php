<?php

namespace App\Infrastructure\Persistence\Doctrine\Task;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskDates;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Infrastructure\Persistence\Doctrine\Preset\PresetEntity;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Persistence\Doctrine\Video\VideoEntity;
use Symfony\Component\Uid\UuidV4 AS SymfonyUuid;

class TaskMapper
{
    public static function toDomain(TaskEntity $entity): Task
    {
        return Task::reconstitute(
            videoId: Uuid::fromString($entity->video->id->toRfc4122()),
            presetId: Uuid::fromString($entity->preset->id->toRfc4122()),
            userId: Uuid::fromString($entity->user->id->toRfc4122()),
            status: TaskStatus::from($entity->status),
            progress: new Progress($entity->progress),
            dates: TaskDates::fromPersistence(
                $entity->createdAt ?? new \DateTimeImmutable(),
                $entity->startedAt,
                $entity->updatedAt,
            ),
            id: Uuid::fromString($entity->id->toRfc4122()),
            meta: $entity->meta,
            deleted: $entity->deleted,
        );
    }

    public static function toDoctrine(Task $task, VideoEntity $video, PresetEntity $preset, UserEntity $user): TaskEntity
    {
        $entity = new TaskEntity();
        if ($task->id() !== null) {
            $entity->id = SymfonyUuid::fromString($task->id()->toRfc4122());
        }
        self::hydrate($entity, $task, $video, $preset, $user);
        $entity->createdAt = $task->createdAt();

        return $entity;
    }

    public static function hydrate(TaskEntity $entity, Task $task, VideoEntity $video, PresetEntity $preset, UserEntity $user): void
    {
        $entity->status = $task->status()->value;
        $entity->progress = $task->progress()->value();
        $entity->startedAt = $task->startedAt();
        $entity->updatedAt = $task->updatedAt();
        $entity->video = $video;
        $entity->preset = $preset;
        $entity->user = $user;
        $entity->meta = $task->meta();
        $entity->deleted = $task->isDeleted();
    }
}
