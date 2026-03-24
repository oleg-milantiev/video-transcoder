<?php

namespace App\Application\Query;

use App\Application\Exception\InvalidUuidException;
use Symfony\Component\Uid\UuidV4;

final readonly class DeleteVideoQuery
{
    public UuidV4 $videoId;
    public UuidV4 $requestedByUserId;

    public function __construct(string $videoId, string $requestedByUserId)
    {
        try {
            $this->videoId = UuidV4::fromString($videoId);
            $this->requestedByUserId = UuidV4::fromString($requestedByUserId);
        } catch (\Throwable $e) {
            throw new InvalidUuidException('Invalid UUID', previous: $e);
        }
    }
}
