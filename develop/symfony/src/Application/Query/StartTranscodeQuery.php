<?php

namespace App\Application\Query;

use App\Application\Exception\InvalidUuidException;
use App\Domain\Shared\ValueObject\Uuid;

final readonly class StartTranscodeQuery
{
    public Uuid $uuid;
    public Uuid $presetId;
    public Uuid $userId;

    public function __construct(string $uuid, string $presetId, string $userId)
    {
        try {
            $this->uuid = Uuid::fromString($uuid);
            $this->presetId = Uuid::fromString($presetId);
            $this->userId = Uuid::fromString($userId);
        } catch (\Throwable $e) {
            throw new InvalidUuidException('Invalid UUID', previous: $e);
        }
    }
}

