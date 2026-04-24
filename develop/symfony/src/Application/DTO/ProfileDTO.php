<?php
declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Video\ValueObject\TaskStatus;

final readonly class ProfileDTO
{
    private function __construct(
        public int $storageDelete24,
        public int $videoCountActive,
        public int $videoCountTotal,
        public int $taskCountActive,
        public int $taskCountTotal,
        public array $taskCountByStatus,
        public string $willStartAt,
    ) {
    }

    public static function create(
        int $storageDelete24,
        int $videoCountActive,
        int $videoCountTotal,
        int $taskCountActive,
        int $taskCountTotal,
        array $taskCountByStatus,
        string $willStartAt,
    ): self {
        return new self(
            storageDelete24: $storageDelete24,
            videoCountActive: $videoCountActive,
            videoCountTotal: $videoCountTotal,
            taskCountActive: $taskCountActive,
            taskCountTotal: $taskCountTotal,
            taskCountByStatus: $taskCountByStatus,
            willStartAt: $willStartAt,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        // todo закинуть бы это в jsonb user.profile и обновлять их порой неспеша в EDA. А тут просто отдать
        return [
            'statistics' => [
                'video' => [
                    'active' => $this->videoCountActive,
                    'total' => $this->videoCountTotal,
                ],
                'task' => [
                    'active' => $this->taskCountActive,
                    'total' => $this->taskCountTotal,
                    'processing' => isset($this->taskCountByStatus[TaskStatus::PROCESSING->value]) ? $this->taskCountByStatus[TaskStatus::PROCESSING->value] : 0,
                    'queue' => (isset($this->taskCountByStatus[TaskStatus::PENDING->value]) ? $this->taskCountByStatus[TaskStatus::PENDING->value] : 0) +
                        (isset($this->taskCountByStatus[TaskStatus::STARTING->value]) ? $this->taskCountByStatus[TaskStatus::STARTING->value] : 0),
                    'completed' => $this->taskCountByStatus[TaskStatus::COMPLETED->value] ?? 0,
                    'willStartAt' => $this->willStartAt,
                ],
            ],
            'storage' => [
                'delete24' => $this->storageDelete24,
            ],
        ];
    }
}
