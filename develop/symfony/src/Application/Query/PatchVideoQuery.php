<?php

namespace App\Application\Query;

use App\Application\Exception\InvalidUuidException;
use App\Domain\Shared\ValueObject\Uuid;

final readonly class PatchVideoQuery
{
    public Uuid $videoId;
    public string $title;
    public Uuid $requestedByUserId;

    public function __construct(string $videoId, string $title, string $requestedByUserId)
    {
        try {
            $this->videoId = Uuid::fromString($videoId);
            $this->requestedByUserId = Uuid::fromString($requestedByUserId);
        } catch (\Throwable $e) {
            throw new InvalidUuidException('Invalid UUID', previous: $e);
        }

        $this->title = $title;
    }
}
