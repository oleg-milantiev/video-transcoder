<?php
declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Service\Storage\StorageInterface;

final class S3Storage implements StorageInterface
{
    public function sourceKey(Video $video): string
    {
        if ($video->id() === null) {
            throw new \DomainException('Video id is not set, cannot build source key.');
        }

        return $video->id()->toRfc4122() . '.' . $video->extension()->value();
    }

    public function previewKey(Video $video): string
    {
        if ($video->id() === null) {
            throw new \DomainException('Video id is not set, cannot build preview key.');
        }

        return $video->id()->toRfc4122() . '.jpg';
    }

    public function taskOutputKey(Video $video, Preset $preset): string
    {
        if ($video->id() === null) {
            throw new \DomainException('Video id is not set, cannot build task output key.');
        }

        return sprintf('%s/%s.mp4', $video->id()->toRfc4122(), $preset->id()->toRfc4122());
    }

    public function putFromPath(string $sourcePath, string $key): string
    {
        throw new \LogicException('S3Storage is not configured yet.');
    }

    public function delete(string $key): bool
    {
        throw new \LogicException('S3Storage is not configured yet.');
    }

    public function publicUrl(string $key): string
    {
        throw new \LogicException('S3Storage is not configured yet.');
    }

    public function localPathForRead(string $key): string
    {
        throw new \LogicException('S3Storage is not configured yet.');
    }

    public function localPathForWrite(string $key): string
    {
        throw new \LogicException('S3Storage is not configured yet.');
    }

    public function publishLocalFile(string $localPath, string $key): void
    {
        throw new \LogicException('S3Storage is not configured yet.');
    }
}

