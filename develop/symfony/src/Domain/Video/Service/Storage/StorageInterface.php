<?php

namespace App\Domain\Video\Service\Storage;

use Symfony\Component\HttpFoundation\File\File;

// TODO use storage MORE
interface StorageInterface
{
    /**
     * @param File $file
     * @param string $path
     * @return string The stored file name/path
     */
    public function upload(File $file, string $path): string;

    /**
     * @param string $path
     * @return bool
     */
    public function delete(string $path): bool;

    /**
     * @param string $path
     * @return string The public URL of the file
     */
    public function getUrl(string $path): string;

    /**
     * @param string $path
     * @return string The absolute path to the file
     */
    public function getAbsolutePath(string $path): string;
}
