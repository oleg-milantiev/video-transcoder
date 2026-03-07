<?php

namespace App\Application\Service;

use App\Domain\User\Entity\User;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
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
