<?php
declare(strict_types=1);

namespace App\Application\QueryHandler;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

class QueryBus
{
    use HandleTrait;

    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        MessageBusInterface $queryBus,
    ) {
        $this->messageBus = $queryBus;
    }

    public function query(object $query): mixed
    {
        try {
            return $this->handle($query);
        } catch (HandlerFailedException $e) {
            // queryBus always handle only one handler, so we can safely throw the first exception
            throw $e->getPrevious() ?? $e;
        }
    }
}
