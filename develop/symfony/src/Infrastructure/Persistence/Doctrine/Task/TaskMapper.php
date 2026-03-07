<?php

namespace App\Infrastructure\Persistence\Doctrine\Task;

use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Entity\Task;
use App\Infrastructure\Persistence\Doctrine\Preset\PresetEntity;

class TaskMapper
{
    public static function toDomain(TaskEntity $entity): Task
    {
        return new Task(
            status: $entity->status,
            progress: $entity->progress,
            createdAt: $entity->createdAt,
            video: $entity->video,
            preset: $entity->preset,
            updatedAt: $entity->updatedAt,
            id: $entity->id,
        );
    }

    public static function toDoctrine(Task $task): TaskEntity
    {
        $entity = new TaskEntity();
        $entity->id = $task->id();
        $entity->status = $task->status();
        $entity->progress = $task->progress();
        $entity->createdAt = $task->createdAt();
        $entity->updatedAt = $task->updatedAt();
        $entity->video = $task->video();
        $entity->preset = $task->preset();

        return $entity;
    }
}
