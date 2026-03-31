<?php
declare(strict_types=1);

namespace App\Application\Query;

use App\Application\Exception\InvalidUuidException;
use App\Domain\Shared\ValueObject\Uuid;

final readonly class DeleteVideoQuery
{
    public Uuid $videoId;
    public Uuid $requestedByUserId;

    public function __construct(string $videoId, string $requestedByUserId)
    {
        try {
            $this->videoId = Uuid::fromString($videoId);
            $this->requestedByUserId = Uuid::fromString($requestedByUserId);
        } catch (\Throwable $e) {
            throw new InvalidUuidException('Invalid UUID', previous: $e);
        }
    }
}
