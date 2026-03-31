<?php
declare(strict_types=1);

namespace App\Application\Query;

use App\Application\Exception\InvalidUuidException;
use App\Application\Exception\QueryException;
use App\Domain\Shared\ValueObject\Uuid;
use Symfony\Component\HttpFoundation\Request;

final readonly class PatchVideoQuery
{
    public Uuid $videoId;
    public string $title;
    public Uuid $requestedByUserId;

    public function __construct(string $videoId, Request $request, string $requestedByUserId)
    {
        try {
            $payload = json_decode($request->getContent() ?: '{}', true);
            $title = isset($payload['title']) ? (string)$payload['title'] : null;

            if ($title === null) {
                throw new QueryException('Missing title', 400);
            }

            $this->videoId = Uuid::fromString($videoId);
            $this->requestedByUserId = Uuid::fromString($requestedByUserId);
            $this->title = $title;
        } catch (\Throwable $e) {
            throw new InvalidUuidException('Invalid UUID', previous: $e);
        }
    }
}
