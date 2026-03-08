<?php

namespace App\Infrastructure\Persistence\Doctrine\Video;

use App\Domain\Video\Entity\Video;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoStatus;
use App\Domain\Video\ValueObject\VideoTitle;
use App\Infrastructure\Persistence\Doctrine\User\UserMapper;

class VideoMapper
{
    public static function toDomain(VideoEntity $entity): Video
    {
        return new Video(
            title: new VideoTitle($entity->title),
            extension: new FileExtension($entity->extension),
            previewPath: $entity->previewPath,
            status: VideoStatus::from($entity->status),
            createdAt: $entity->createdAt,
            user: UserMapper::toDomain($entity->user),
            id: $entity->id?->toString(),
        );
    }

    public static function toDoctrine(Video $video): VideoEntity
    {
        $entity = new VideoEntity();
        $entity->id = $video->id();
        $entity->title = $video->title()->value();
        $entity->extension = $video->extension()->value();
        $entity->user = UserMapper::toDoctrine($video->user());
        $entity->status = $video->status()->value;
        $entity->createdAt = $video->createdAt();
        $entity->updatedAt = $video->updatedAt();
        $entity->previewPath = $video->previewPath();
        $entity->tasks = $video->tasks();

        return $entity;
    }
}
