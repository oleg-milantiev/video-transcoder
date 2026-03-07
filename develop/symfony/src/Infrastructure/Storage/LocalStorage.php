<?php

namespace App\Infrastructure\Storage;

use App\Domain\Video\Service\Storage\StorageInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;

class LocalStorage implements StorageInterface
{
    public function __construct(
        private readonly string $storagePath,
        private readonly string $publicPath,
        private readonly Filesystem $filesystem
    ) {
    }

    public function upload(File $file, string $path): string
    {
        $targetPath = $this->storagePath . DIRECTORY_SEPARATOR . $path;
        $directory = dirname($targetPath);
        $filename = basename($targetPath);

        if (!$this->filesystem->exists($directory)) {
            $this->filesystem->mkdir($directory);
        }

        $file->move($directory, $filename);

        return $path;
    }

    public function delete(string $path): bool
    {
        $fullPath = $this->storagePath . DIRECTORY_SEPARATOR . $path;
        if ($this->filesystem->exists($fullPath)) {
            $this->filesystem->remove($fullPath);
            return true;
        }

        return false;
    }

    public function getUrl(string $path): string
    {
        return $this->publicPath . DIRECTORY_SEPARATOR . $path;
    }
}
