<?php

namespace App\Application\Query;

use App\Application\Exception\InvalidUuidException;
use Symfony\Component\Uid\UuidV4;

final readonly class StartTranscodeQuery
{
    public UuidV4 $uuid;
    public UuidV4 $presetId;
    public UuidV4 $userId;

    public function __construct(string $uuid, string $presetId, UuidV4 $userId)
    {
        try {
            $this->uuid = UuidV4::fromString($uuid);
            $this->presetId = UuidV4::fromString($presetId);
        } catch (\Throwable $e) {
            throw new InvalidUuidException('Invalid UUID', previous: $e);
        }

        $this->userId = $userId;
    }
}

