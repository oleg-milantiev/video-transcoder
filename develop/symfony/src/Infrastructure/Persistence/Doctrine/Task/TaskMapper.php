<?php

namespace App\Infrastructure\Persistence\Doctrine\Task;

use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Infrastructure\Persistence\Doctrine\Preset\PresetMapper;
use App\Infrastructure\Persistence\Doctrine\Video\VideoMapper;
use App\Infrastructure\Persistence\Doctrine\User\UserMapper;

class TaskMapper
{
    public static function toDomain(TaskEntity $entity): Task
    {
        return new Task(
            video: VideoMapper::toDomain($entity->video),
            preset: PresetMapper::toDomain($entity->preset),
            status: TaskStatus::from($entity->status),
            progress: new Progress($entity->progress),
            createdAt: $entity->createdAt,
            updatedAt: $entity->updatedAt,
            id: $entity->id,
            meta: $entity->meta,
            user: $entity->user ? UserMapper::toDomain($entity->user) : null,
        );
    }

    public static function toDoctrine(Task $task): TaskEntity
    {
        $entity = new TaskEntity();
        $entity->id = $task->id();
        $entity->status = $task->status()->value;
        $entity->progress = $task->progress()->value();
        $entity->createdAt = $task->createdAt();
        $entity->updatedAt = $task->updatedAt();
        // TODO move to Ids
        $entity->video = VideoMapper::toDoctrine($task->video());
        $entity->preset = PresetMapper::toDoctrine($task->preset());
        $entity->meta = $task->meta();
        $entity->user = $task->user() ? UserMapper::toDoctrine($task->user()) : null;

        return $entity;
    }
}
