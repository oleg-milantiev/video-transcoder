<?php
declare(strict_types=1);

namespace App\Application\DTO;

final readonly class StorageRealtimePayloadDTO
{
    public function __construct(
        public int $storageNow,
        public int $storageMax,
    ) {
    }

    public static function fromSizes(int $storageNow, int $storageMax): self
    {
        return new self($storageNow, $storageMax);
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'storageNow' => $this->storageNow,
            'storageMax' => $this->storageMax,
        ];
    }
}
