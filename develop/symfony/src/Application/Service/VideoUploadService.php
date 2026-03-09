<?php

namespace App\Application\Service;

use App\Domain\User\Entity\User;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Event\VideoCreated;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoStatus;
use App\Domain\Video\ValueObject\VideoTitle;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class VideoUploadService
{
    public function __construct(
        private StorageInterface         $storage,
        private VideoRepositoryInterface $videoRepository,
        private MessageBusInterface      $messageBus
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function upload(File $file, string $title, User $user): Video
    {
        $extension = $file->guessExtension() ?: 'mp4';

        $video = new Video(
            title: new VideoTitle($title),
            extension: new FileExtension($extension),
            status: VideoStatus::UPLOADED,
            createdAt: new \DateTimeImmutable(),
            user: $user
        );

        $this->storage->upload($file, $video->getSrcFilename());

        $this->videoRepository->save($video, true);

        $this->messageBus->dispatch(new VideoCreated($video));

        return $video;
    }

//    public function delete(Video $video): void
//    {
//        $this->storage->delete($video->getSrcFilename());
//
//        $this->videoRepository->remove($video, true);
//    }
}
