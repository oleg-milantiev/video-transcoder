<?php

namespace App\Application\CommandHandler\Video;

use App\Application\Command\Video\CreateVideo;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

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
            $this->logger->info('Create Video Handler start', [
                'file' => $command->file()->details(),
            ]);

            $video = Video::createFromCommand($command);
            $this->videoRepository->save($video);

//            dd($command);
//
//            $this->messageBus->dispatch(new VideoCreationFinished($videoId));
        } catch (\Exception $e) {
//            throw VideoCreationFailed::fromVideoId($videoId->toString(), $e->getMessage());
        }
    }
}
