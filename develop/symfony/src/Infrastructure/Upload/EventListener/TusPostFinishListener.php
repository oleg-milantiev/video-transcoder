<?php

namespace App\Infrastructure\Upload\EventListener;

use App\Application\Command\Video\CreateVideo;
use App\Domain\Shared\ValueObject\Uuid;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use TusPhp\Events\UploadComplete;

// DDD way for connect to Tus infrastructure uploader
readonly class TusPostFinishListener
{
    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
        private Security $security,
    ) {}

    /**
     * @throws ExceptionInterface
     */
    #[AsEventListener(event: UploadComplete::NAME)]
    public function __invoke(UploadComplete $event): void
    {
        $this->commandBus->dispatch(new CreateVideo(
            file: $event->getFile(),
            userId: Uuid::fromString($this->security->getUser()->id->toRfc4122()),
        ));
    }
}
