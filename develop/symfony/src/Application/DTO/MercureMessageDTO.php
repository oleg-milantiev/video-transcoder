<?php
declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Shared\ValueObject\Uuid;

final readonly class MercureMessageDTO
{
    /**
     * @param array<string, mixed>|null $payload
     */
    public function __construct(
        public string $action,
        public string $entity,
        public Uuid $id,
        public Uuid $userId,
        public ?array $payload = null,
    ) {
    }
}
