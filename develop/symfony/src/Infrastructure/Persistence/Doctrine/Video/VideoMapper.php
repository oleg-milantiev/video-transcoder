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
            id: $entity->id,
        );
    }

    public static function toDoctrine(Video $video, UserEntity $user): VideoEntity
    {
        $entity = new VideoEntity();
        $entity->id = $video->id();
        self::hydrate($entity, $video, $user);
        $entity->createdAt = $video->createdAt();

        return $entity;
    }

    public static function hydrate(VideoEntity $entity, Video $video, UserEntity $user): void
    {
        $entity->title = $video->title()->value();
        $entity->extension = $video->extension()->value();
        $entity->user = $user;
        $entity->status = $video->status()->value;
        $entity->meta = $video->meta();
        $entity->updatedAt = $video->updatedAt();
    }
}
