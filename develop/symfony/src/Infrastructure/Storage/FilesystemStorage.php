<?php

namespace App\Infrastructure\Storage;

use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Service\Storage\StorageInterface;
use Symfony\Component\Filesystem\Filesystem;

final class FilesystemStorage implements StorageInterface
{
    public function __construct(
        private readonly string $storagePath,
        private readonly string $publicPath,
        private readonly Filesystem $filesystem
    ) {
    }

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
        $targetPath = $this->storagePath . DIRECTORY_SEPARATOR . $key;
        $directory = dirname($targetPath);

        if (!$this->filesystem->exists($directory)) {
            $this->filesystem->mkdir($directory);
        }

        $this->filesystem->rename($sourcePath, $targetPath, true);

        return $key;
    }

    public function delete(string $key): bool
    {
        $fullPath = $this->storagePath . DIRECTORY_SEPARATOR . $key;
        if ($this->filesystem->exists($fullPath)) {
            $this->filesystem->remove($fullPath);
            return true;
        }

        return false;
    }

    public function publicUrl(string $key): string
    {
        return rtrim($this->publicPath, '/') . '/' . ltrim($key, '/');
    }

    public function localPathForRead(string $key): string
    {
        return $this->storagePath . DIRECTORY_SEPARATOR . $key;
    }

    public function localPathForWrite(string $key): string
    {
        $absolutePath = $this->storagePath . DIRECTORY_SEPARATOR . $key;
        $directory = dirname($absolutePath);

        if (!$this->filesystem->exists($directory)) {
            $this->filesystem->mkdir($directory);
        }

        return $absolutePath;
    }

    public function publishLocalFile(string $localPath, string $key): void
    {
        $targetPath = $this->storagePath . DIRECTORY_SEPARATOR . $key;
        $directory = dirname($targetPath);

        if (!$this->filesystem->exists($directory)) {
            $this->filesystem->mkdir($directory);
        }

        if ($localPath !== $targetPath) {
            $this->filesystem->rename($localPath, $targetPath, true);
        }
    }
}
