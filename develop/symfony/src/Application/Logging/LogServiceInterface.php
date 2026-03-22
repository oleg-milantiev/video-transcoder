<?php

namespace App\Application\Logging;

use Symfony\Component\Uid\Uuid;

interface LogServiceInterface
{
    /**
     * @param array<string, mixed> $context
     * @param string $level One of Psr\Log\LogLevel::* constants.
     */
    public function log(string $name, Uuid $objectId, string $level, string $text, array $context = []): void;
}
