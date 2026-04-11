<?php
declare(strict_types=1);

namespace App\Application\DTO;

readonly class TaskInfoDTO
{
    public function __construct(
        public string $status,
        public int $progress,
        public string $createdAt,
        public ?bool $waitingTariffInstance,
        public ?bool $waitingTariffDelay,
        public ?string $willStartAt,
        public ?string $id = null,
    ) {}
}
