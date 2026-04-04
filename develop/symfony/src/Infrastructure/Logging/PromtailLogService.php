<?php
declare(strict_types=1);

namespace App\Infrastructure\Logging;

use App\Application\Logging\LogServiceInterface;
use App\Domain\Shared\ValueObject\Uuid;
use Psr\Log\LoggerInterface;

final readonly class PromtailLogService implements LogServiceInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function log(string $name, string $action, Uuid $objectId, string $level, string $text, array $context = []): void
    {
        $this->logger->log($level, $text, array_merge($context, [
            'action' => $action,
            'name' => $name,
            'uuid' => $objectId->toRfc4122(),
        ]));
    }
}
