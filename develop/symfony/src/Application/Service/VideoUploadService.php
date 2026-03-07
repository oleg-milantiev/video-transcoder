<?php

namespace App\Application\Service;

use App\Domain\Repository\VideoRepositoryInterface;
use App\Domain\Storage\StorageInterface;
use App\Entity\Video;
use App\Entity\User;
use Symfony\Component\HttpFoundation\File\File;

class VideoUploadService
{
    public function __construct(
        private readonly StorageInterface $storage,
        private readonly VideoRepositoryInterface $videoRepository
    ) {
    }

    public function upload(File $file, string $title, User $user): Video
    {
        $video = new Video();
        $video->title = $title;
        $video->user = $user;
        $video->status = 'pending';

        $extension = $file->guessExtension();
        $video->extension = $extension;

        $this->storage->upload($file, $video->getSrcFilename());

        $this->videoRepository->save($video, true);

        return $video;
    }

    public function delete(Video $video): void
    {
        $this->storage->delete($video->getSrcFilename());

        $this->videoRepository->remove($video, true);
    }
}
