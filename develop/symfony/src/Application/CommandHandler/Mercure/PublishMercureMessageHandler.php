<?php

declare(strict_types=1);

namespace App\Application\CommandHandler\Mercure;

use App\Application\Command\Mercure\PublishMercureMessage;
use App\Application\Service\Mercure\MercurePublisherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class PublishMercureMessageHandler
{
    public function __construct(private MercurePublisherInterface $publisher)
    {
    }

    public function __invoke(PublishMercureMessage $command): void
    {
        $this->publisher->publish($command->message);
    }
}

