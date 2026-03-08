<?php

namespace App\Application\EventSubscriber;

use App\Application\Command\Video\CreateVideoPreview;
use App\Domain\Video\Event\VideoUploaded;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class VideoUploadedSubscriber
{
    public function __construct(
        private MessageBusInterface $messageBus
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    #[AsMessageHandler]
    public function __invoke(VideoUploaded $event): void
    {
        $video = $event->video();
        $videoId = $video->id();

        if ($videoId !== null) {
            $this->messageBus->dispatch(new CreateVideoPreview($videoId));
        }
    }
}
