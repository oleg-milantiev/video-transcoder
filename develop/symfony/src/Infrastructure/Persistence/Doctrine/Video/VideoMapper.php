<?php

namespace App\Infrastructure\Persistence\Doctrine\Video;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoTitle;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use Symfony\Component\Uid\UuidV4 AS SymfonyUuid;

class VideoMapper
{
    public static function toDomain(VideoEntity $entity): Video
    {
        return Video::reconstitute(
            title: new VideoTitle($entity->title),
            extension: new FileExtension($entity->extension),
            userId: Uuid::fromString($entity->user->id->toRfc4122()),
            meta: $entity->meta,
            dates: VideoDates::fromPersistence($entity->createdAt, $entity->updatedAt),
            id: Uuid::fromString($entity->id->toRfc4122()),
            deleted: $entity->deleted,
        );
    }

    public static function toDoctrine(Video $video, UserEntity $user): VideoEntity
    {
        $entity = new VideoEntity();
        if ($video->id() !== null) {
            $entity->id = SymfonyUuid::fromString($video->id()->toRfc4122());
        }
        self::hydrate($entity, $video, $user);
        $entity->createdAt = $video->createdAt();

        return $entity;
    }

    public static function hydrate(VideoEntity $entity, Video $video, UserEntity $user): void
    {
        $entity->title = $video->title()->value();
        $entity->extension = $video->extension()->value();
        $entity->user = $user;
        $entity->meta = $video->meta();
        $entity->deleted = $video->isDeleted();
        $entity->updatedAt = $video->updatedAt();
    }
}
