<?php

namespace App\Infrastructure\Upload\EventListener;

use App\Application\Command\Video\CreateVideo;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use TusPhp\Events\UploadComplete;

// DDD way for connect to Tus infrastructure uploader
readonly class TusPostFinishListener
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private Security $security,
    ) {}

    /**
     * @throws ExceptionInterface
     */
    #[AsEventListener(event: UploadComplete::NAME)]
    public function __invoke(UploadComplete $event): void
    {
        $this->messageBus->dispatch(new CreateVideo(
            file: $event->getFile(),
            userId: $this->security->getUser()->id,
        ));
    }
}
