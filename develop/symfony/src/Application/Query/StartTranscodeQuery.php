<?php

namespace App\Application\Query;

use App\Application\Exception\InvalidUuidException;
use Symfony\Component\Uid\UuidV4;

final readonly class StartTranscodeQuery
{
    public UuidV4 $uuid;
    public int $presetId;
    public int $userId;

    public function __construct(string $uuid, int $presetId, int $userId)
    {
        try {
            $this->uuid = UuidV4::fromString($uuid);
        } catch (\Throwable $e) {
            throw new InvalidUuidException('Invalid UUID', previous: $e);
        }

        $this->presetId = $presetId;
        $this->userId = $userId;
    }
}

