<?php

namespace App\Application\Service;

use App\Domain\User\Entity\User;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoStatus;
use App\Domain\Video\ValueObject\VideoTitle;
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
        $extension = $file->guessExtension() ?: 'mp4';

        $video = new Video(
            title: new VideoTitle($title),
            extension: new FileExtension($extension),
            previewPath: '', // По умолчанию пусто
            status: VideoStatus::PENDING,
            createdAt: new \DateTimeImmutable(),
            user: $user
        );

        $this->storage->upload($file, $video->getSrcFilename());

        $this->videoRepository->save($video, true);

        return $video;
    }

//    public function delete(Video $video): void
//    {
//        $this->storage->delete($video->getSrcFilename());
//
//        $this->videoRepository->remove($video, true);
//    }
}
