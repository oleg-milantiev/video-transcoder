<?php

namespace App\Infrastructure\Persistence\Doctrine\Task;

use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Infrastructure\Persistence\Doctrine\Preset\PresetMapper;
use App\Infrastructure\Persistence\Doctrine\Video\VideoMapper;

class TaskMapper
{
    public static function toDomain(TaskEntity $entity): Task
    {
        return new Task(
            video: VideoMapper::toDomain($entity->video),
            preset: PresetMapper::toDomain($entity->preset),
            status: TaskStatus::from($entity->status),
            progress: new \App\Domain\Video\ValueObject\Progress($entity->progress),
            createdAt: $entity->createdAt,
            updatedAt: $entity->updatedAt,
            id: $entity->id,
            meta: $entity->meta,
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
        $entity->video = VideoMapper::toDoctrine($task->video());
        $entity->preset = PresetMapper::toDoctrine($task->preset());
        $entity->meta = $task->meta();

        return $entity;
    }
}
