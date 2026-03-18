<?php

namespace App\Application\CommandHandler\Video;

use App\Application\Command\Video\CreateVideo;
use App\Application\Command\Video\ExtractVideoMetadata;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\HttpFoundation\File\File;

// TODO test
#[AsMessageHandler]
final readonly class CreateVideoHandler
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
        private VideoRepositoryInterface $videoRepository,
        private StorageInterface $storage,
    ) {
    }

    public function __invoke(CreateVideo $command): void
    {
        try {
            $this->logger->debug('Create Video: started');

            $video = Video::createFromCommand($command);
            $this->logger->debug('Create Video: video created from command', [
                'video' => print_r($video, true),
            ]);
            $video = $this->videoRepository->save($video);

            $this->logger->debug('Create Video: video saved', [
                'video' => print_r($video, true),
            ]);

            $this->storage->upload(
                new File($command->file()->getFilePath()),
                $video->getSrcFilename(),
            );

            $this->logger->debug('Create Video: file moved to '. $video->getSrcFilename(), [
                'video' => print_r($video, true),
            ]);

            // TODO split command and event message busses
//            $this->messageBus->dispatch(new VideoCreated($video));
            $this->videoRepository->log($video->id(), 'info', 'Video created', [
                'video' => print_r($video, true),
                'file' => $command->file()->details(),
            ]);

            $this->logger->debug('Create Video: log saved');

            $this->messageBus->dispatch(new ExtractVideoMetadata($video));
        } catch (\Exception $e) {
            // TODO split command and event message busses
            //$this->messageBus->dispatch(new VideoCreateFailed($command));

            $this->logger->error('Create Video Handler failed', [
                'file' => $command->file()->details(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
