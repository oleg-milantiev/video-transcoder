<?php

namespace App\Application\EventSubscriber;

use App\Application\Command\Video\CreateVideoPreview;
use App\Domain\Video\Event\VideoMetadataExtractionFinished;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class VideoMetadataExtractionSubscriber
{
    public function __construct(
        private MessageBusInterface $messageBus
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    #[AsMessageHandler]
    public function __invoke(VideoMetadataExtractionFinished $event): void
    {
        $this->messageBus->dispatch(new CreateVideoPreview($event->videoId()));
    }
}
