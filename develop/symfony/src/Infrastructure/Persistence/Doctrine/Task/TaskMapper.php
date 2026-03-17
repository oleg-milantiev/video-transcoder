<?php

namespace App\Infrastructure\Persistence\Doctrine\Task;

use App\Domain\Video\Entity\Task;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Infrastructure\Persistence\Doctrine\Preset\PresetEntity;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Persistence\Doctrine\Video\VideoEntity;

class TaskMapper
{
    public static function toDomain(TaskEntity $entity): Task
    {
        return new Task(
            videoId: $entity->video->id,
            presetId: $entity->preset->id,
            userId: $entity->user->id,
            status: TaskStatus::from($entity->status),
            progress: new Progress($entity->progress),
            createdAt: $entity->createdAt,
            updatedAt: $entity->updatedAt,
            id: $entity->id,
            meta: $entity->meta,
        );
    }

    public static function toDoctrine(Task $task, VideoEntity $video, PresetEntity $preset, UserEntity $user): TaskEntity
    {
        $entity = new TaskEntity();
        $entity->id = $task->id();
        $entity->status = $task->status()->value;
        $entity->progress = $task->progress()->value();
        $entity->createdAt = $task->createdAt();
        $entity->updatedAt = $task->updatedAt();
        $entity->video = $video;
        $entity->preset = $preset;
        $entity->meta = $task->meta();
        $entity->user = $user;

        return $entity;
    }
}
