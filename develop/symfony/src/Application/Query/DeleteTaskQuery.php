<?php

namespace App\Application\Query;

use App\Application\Exception\InvalidUuidException;
use Symfony\Component\Uid\UuidV4;

final readonly class DeleteTaskQuery
{
    public UuidV4 $taskId;
    public UuidV4 $requestedByUserId;

    public function __construct(string $taskId, string $requestedByUserId)
    {
        try {
            $this->taskId = UuidV4::fromString($taskId);
            $this->requestedByUserId = UuidV4::fromString($requestedByUserId);
        } catch (\Throwable $e) {
            throw new InvalidUuidException('Invalid UUID', previous: $e);
        }
    }
}
