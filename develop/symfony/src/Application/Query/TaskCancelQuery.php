<?php
declare(strict_types=1);

namespace App\Application\Query;

use App\Application\Exception\InvalidUuidException;
use App\Domain\Shared\ValueObject\Uuid;

final readonly class TaskCancelQuery
{
    public Uuid $taskId;
    public Uuid $requestedByUserId;

    public function __construct(string $taskId, string $requestedByUserId)
    {
        try {
            $this->taskId = Uuid::fromString($taskId);
            $this->requestedByUserId = Uuid::fromString($requestedByUserId);
        } catch (\Throwable $e) {
            throw new InvalidUuidException('Invalid UUID', previous: $e);
        }
    }
}
