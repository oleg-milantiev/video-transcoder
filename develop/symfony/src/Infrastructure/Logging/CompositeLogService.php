<?php

namespace App\Infrastructure\Logging;

use App\Application\Logging\LogServiceInterface;
use App\Domain\Shared\ValueObject\Uuid;

class CompositeLogService implements LogServiceInterface
{
    /**
     * @param iterable<LogServiceInterface> $loggers
     */
    public function __construct(
        private readonly iterable $loggers
    ) {}

    public function log(string $name, string $action, ?Uuid $objectId, string $level, string $text, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->log($name, $action, $objectId, $level, $text, $context);
        }
    }
}
