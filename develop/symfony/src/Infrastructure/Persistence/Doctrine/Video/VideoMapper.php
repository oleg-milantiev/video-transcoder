<?php

namespace App\Infrastructure\Persistence\Doctrine\Video;

use App\Domain\Video\Entity\Video;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoStatus;
use App\Domain\Video\ValueObject\VideoTitle;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;

class VideoMapper
{
    public static function toDomain(VideoEntity $entity): Video
    {
        return new Video(
            title: new VideoTitle($entity->title),
            extension: new FileExtension($entity->extension),
            status: VideoStatus::from($entity->status),
            userId: $entity->user->id,
            createdAt: $entity->createdAt,
            meta: $entity->meta,
            id: $entity->id?->toString(),
        );
    }

    public static function toDoctrine(Video $video, UserEntity $user): VideoEntity
    {
        $entity = new VideoEntity();
        $entity->id = $video->id();
        $entity->title = $video->title()->value();
        $entity->extension = $video->extension()->value();
        $entity->user = $user;
        $entity->status = $video->status()->value;
        $entity->meta = $video->meta();
        $entity->createdAt = $video->createdAt();
        $entity->updatedAt = $video->updatedAt();

        return $entity;
    }
}
