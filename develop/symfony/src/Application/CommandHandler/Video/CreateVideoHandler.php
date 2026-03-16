<?php

namespace App\Application\CommandHandler\Video;

use App\Application\Command\Video\CreateVideo;
use App\Application\Command\Video\ExtractVideoMetadata;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Event\VideoCreated;
use App\Domain\Video\Event\VideoCreateFailed;
use App\Domain\Video\Repository\VideoRepositoryInterface;
//use App\Domain\Video\Service\Storage\StorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\HttpFoundation\File\File;

#[AsMessageHandler]
final readonly class CreateVideoHandler
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
        private VideoRepositoryInterface $videoRepository,
    ) {
    }

    public function __invoke(CreateVideo $command): void
    {
        try {
            $video = Video::createFromCommand($command);
            $this->videoRepository->save($video);

            $this->messageBus->dispatch(new VideoCreated($video));
            $this->videoRepository->log($video->id(), 'info', 'Video created', [
                'file' => $command->file()->details(),
            ]);

            $this->messageBus->dispatch(new ExtractVideoMetadata($video));
        } catch (\Exception $e) {
            $this->messageBus->dispatch(new VideoCreateFailed($command));

            $this->logger->error('Create Video Handler failed', [
                'file' => $command->file()->details(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
