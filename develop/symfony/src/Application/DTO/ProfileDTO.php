<?php
declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Helper\HumanReadableHelper;
use App\Domain\User\Entity\User;

final readonly class ProfileDTO
{
    private function __construct(
        public int $storageDelete24,
    ) {
    }

    public static function create(
        int $storageDelete24,
    ): self {
        return new self(
            storageDelete24: $storageDelete24,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'storage' => [
                'delete24' => $this->storageDelete24,
            ],
        ];
    }
}
