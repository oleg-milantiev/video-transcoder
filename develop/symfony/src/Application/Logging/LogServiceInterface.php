<?php
declare(strict_types=1);

namespace App\Application\Logging;

use App\Domain\Shared\ValueObject\Uuid;

interface LogServiceInterface
{
    /**
     * @param array<string, mixed> $context
     * @param string $level One of Psr\Log\LogLevel::* constants.
     */
    public function log(string $name, Uuid $objectId, string $level, string $text, array $context = []): void;
}
