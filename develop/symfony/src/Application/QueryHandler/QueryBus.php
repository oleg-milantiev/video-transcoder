<?php
declare(strict_types=1);

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
        // todo может быть тут разворачивать HandlerFailedException до $e->getPrevious()?
        return $this->handle($query);
    }
}
