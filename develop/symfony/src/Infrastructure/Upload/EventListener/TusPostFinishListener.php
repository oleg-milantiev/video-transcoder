<?php

namespace App\Infrastructure\Upload\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;
use TusPhp\Events\TusEvent;

// DDD way for connect to Tus infrastructure uploader
readonly class TusPostFinishListener
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {}

    #[AsEventListener(event: 'tphp.post_finish')]
    public function __invoke(TusEvent $event): void
    {
        $this->messageBus->dispatch(new CreateVideo($event->getFile()));
    }
}
