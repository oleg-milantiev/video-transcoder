<?php

namespace App\Application\Event;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class ApplicationEventLoggerHandler
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(ApplicationEvent $event): void
    {
        $eventClass = $event::class;
        $eventName = new \ReflectionClass($event)->getShortName();
        $context = [
            'eventClass' => $eventClass,
            'payload' => get_object_vars($event),
        ];

        if (str_ends_with($eventName, 'Fail')) {
            $this->logger->error('Application event dispatched', $context);
            return;
        }

        if (str_ends_with($eventName, 'Start') || str_ends_with($eventName, 'Success')) {
            $this->logger->info('Application event dispatched', $context);
            return;
        }

        $this->logger->info('Application event dispatched', $context);
    }
}

