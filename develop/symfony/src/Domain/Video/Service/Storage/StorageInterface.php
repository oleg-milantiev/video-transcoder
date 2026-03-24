<?php

namespace App\Domain\Video\Service\Storage;

use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Entity\Video;

interface StorageInterface
{
    public function sourceKey(Video $video): string;

    public function previewKey(Video $video): string;

    public function taskOutputKey(Video $video, Preset $preset): string;

    public function putFromPath(string $sourcePath, string $key): string;

    public function delete(string $key): bool;

    public function publicUrl(string $key): string;

    public function localPathForRead(string $key): string;

    public function localPathForWrite(string $key): string;

    public function publishLocalFile(string $localPath, string $key): void;
}
