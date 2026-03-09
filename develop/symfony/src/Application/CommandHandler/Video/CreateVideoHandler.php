<?php

namespace App\Application\CommandHandler\Video;

use App\Application\Command\Video\CreateVideo;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class CreateVideoHandler
{
    public function __construct(
        private MessageBusInterface $messageBus
    ) {
    }

    public function __invoke(CreateVideo $command): void
    {
        try {
            // TODO
            dd($command);

            $this->messageBus->dispatch(new VideoCreationFinished($videoId));
        } catch (\Exception $e) {
            throw VideoCreationFailed::fromVideoId($videoId->toString(), $e->getMessage());
        }
    }
}
