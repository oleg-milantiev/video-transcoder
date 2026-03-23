<?php

declare(strict_types=1);

namespace App\Application\DTO;

use Symfony\Component\Uid\UuidV4;

final readonly class MercureMessageDTO
{
    /**
     * @param array<string, mixed>|null $payload
     */
    public function __construct(
        public string $action,
        public string $entity,
        public UuidV4 $id,
        public UuidV4 $userId,
        public ?array $payload = null,
    ) {
    }
}
