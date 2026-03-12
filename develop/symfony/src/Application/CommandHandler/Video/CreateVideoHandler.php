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
//        private StorageInterface $storage,
        private VideoRepositoryInterface $videoRepository,
    ) {
    }

    public function __invoke(CreateVideo $command): void
    {
        try {
            // TODO @debug
            $this->logger->info('Create Video Handler start', [
                'file' => $command->file()->details(),
            ]);

            $video = Video::createFromCommand($command);
            $this->videoRepository->save($video);

//            $this->storage->upload(
//                new File($command->file()->getFilePath()),
//                $video->getSrcFilename(),
//            );

            // TODO catch it!
            $this->messageBus->dispatch(new VideoCreated($video));

            // TODO @debug
            $this->logger->info('Create Video Handler end');

            $this->messageBus->dispatch(new ExtractVideoMetadata($video));
        } catch (\Exception $e) {
            // TODO catch it!
            $this->messageBus->dispatch(new VideoCreateFailed($command));

            // TODO @debug
            $this->logger->error('Create Video Handler failed', [
                'file' => $command->file()->details(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
