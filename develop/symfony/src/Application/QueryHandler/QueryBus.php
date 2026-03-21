<?php

namespace App\Application\QueryHandler;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        return $this->handle($query);
    }
}
