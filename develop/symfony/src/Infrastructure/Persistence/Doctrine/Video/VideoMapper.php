<?php

namespace App\Infrastructure\Persistence\Doctrine\Video;

use App\Domain\Video\Entity\Video;

class VideoMapper
{
    public static function toDomain(VideoEntity $entity): Video
    {
        return new Video(
            title: $entity->title,
            extension: $entity->extension,
            previewPath: $entity->previewPath,
            status: $entity->status,
            createdAt: $entity->createdAt,
            user: $entity->user,
            id: $entity->id,
        );
    }

    public static function toDoctrine(Video $video): VideoEntity
    {
        $entity = new VideoEntity();
        $entity->id = $video->id();
        $entity->title = $video->title();
        $entity->extension = $video->extension();
        $entity->user = $video->user();
        $entity->status = $video->status();
        $entity->createdAt = $video->createdAt();
        $entity->updatedAt = $video->updatedAt();
        $entity->previewPath = $video->previewPath();
        $entity->tasks = $video->tasks();

        return $entity;
    }
}
