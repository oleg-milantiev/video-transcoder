<?php
declare(strict_types=1);

namespace App\Domain\User\ValueObject;

use DateTimeImmutable;
use DateTimeInterface;
use DomainException;

/**
 * Denormalized snapshot of user statistics and billing data persisted in the
 * JSONB `user.profile` column. Updated asynchronously via EDA events.
 *
 * Statistics section  — mirrors ProfileDTO counts (videos, tasks, storage).
 * Billing section     — last subscription window + recent payment history.
 */
final readonly class UserProfile
{
    /**
     * @param array<string|int, int> $taskCountByStatus taskStatus value → count
     * @param list<array<string, string|int|null>> $paymentHistory chronological, newest first
     */
    private function __construct(
        // ── Statistics ──────────────────────────────────────────────────────
        public int $videoCountActive,
        public int $videoCountTotal,
        public int $taskCountActive,
        public int $taskCountTotal,
        public array $taskCountByStatus,
        public int $storageUsedBytes,
        public int $storageDelete24Bytes,
        public string $willStartAt,
        // ── Billing ─────────────────────────────────────────────────────────
        public ?DateTimeImmutable $paidAt,
        public ?DateTimeImmutable $paidUntil,
        public array $paymentHistory,
        // ── Security (encrypted) ─────────────────────────────────────────────
        public ?string $cf = null,
    ) {
        if ($this->videoCountActive < 0 || $this->videoCountTotal < 0) {
            throw new DomainException('Video counts cannot be negative.');
        }

        if ($this->taskCountActive < 0 || $this->taskCountTotal < 0) {
            throw new DomainException('Task counts cannot be negative.');
        }

        if ($this->storageUsedBytes < 0 || $this->storageDelete24Bytes < 0) {
            throw new DomainException('Storage byte values cannot be negative.');
        }

        if ($this->paidAt !== null && $this->paidUntil !== null
            && $this->paidUntil < $this->paidAt) {
            throw new DomainException('paidUntil cannot be earlier than paidAt.');
        }
    }

    // ── Factories ────────────────────────────────────────────────────────────

    /**
     * @param array<string|int, int> $taskCountByStatus
     * @param list<array<string, string|int|null>> $paymentHistory
     */
    public static function create(
        int $videoCountActive,
        int $videoCountTotal,
        int $taskCountActive,
        int $taskCountTotal,
        array $taskCountByStatus,
        int $storageUsedBytes,
        int $storageDelete24Bytes,
        string $willStartAt,
        ?DateTimeImmutable $paidAt = null,
        ?DateTimeImmutable $paidUntil = null,
        array $paymentHistory = [],
        ?string $cf = null,
    ): self {
        return new self(
            videoCountActive: $videoCountActive,
            videoCountTotal: $videoCountTotal,
            taskCountActive: $taskCountActive,
            taskCountTotal: $taskCountTotal,
            taskCountByStatus: $taskCountByStatus,
            storageUsedBytes: $storageUsedBytes,
            storageDelete24Bytes: $storageDelete24Bytes,
            willStartAt: $willStartAt,
            paidAt: $paidAt,
            paidUntil: $paidUntil,
            paymentHistory: $paymentHistory,
            cf: $cf,
        );
    }

    /** Zero-filled profile for newly registered users. */
    public static function empty(): self
    {
        return new self(
            videoCountActive: 0,
            videoCountTotal: 0,
            taskCountActive: 0,
            taskCountTotal: 0,
            taskCountByStatus: [],
            storageUsedBytes: 0,
            storageDelete24Bytes: 0,
            willStartAt: '',
            paidAt: null,
            paidUntil: null,
            paymentHistory: [],
        );
    }

    /** Restore from JSONB array stored in the database. */
    public static function fromArray(array $data): self
    {
        $stats = $data['statistics'] ?? [];
        $video = $stats['video'] ?? [];
        $task = $stats['task'] ?? [];
        $storage = $data['storage'] ?? [];
        $billing = $data['billing'] ?? [];

        return new self(
            videoCountActive: (int)($video['active'] ?? 0),
            videoCountTotal: (int)($video['total'] ?? 0),
            taskCountActive: (int)($task['active'] ?? 0),
            taskCountTotal: (int)($task['total'] ?? 0),
            taskCountByStatus: (array)($task['byStatus'] ?? []),
            storageUsedBytes: (int)($storage['usedBytes'] ?? 0),
            storageDelete24Bytes: (int)($storage['delete24Bytes'] ?? 0),
            willStartAt: (string)($task['willStartAt'] ?? ''),
            paidAt: self::parseDateOrNull($billing['paidAt'] ?? null),
            paidUntil: self::parseDateOrNull($billing['paidUntil'] ?? null),
            paymentHistory: (array)($billing['history'] ?? []),
            cf: isset($data['cf']) && is_string($data['cf']) ? $data['cf'] : null,
        );
    }

    // ── Serialization ─────────────────────────────────────────────────────────

    private static function parseDateOrNull(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $dt = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, (string)$value);

        return $dt !== false ? $dt : null;
    }

    // ── Immutable mutators ────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'statistics' => [
                'video' => [
                    'active' => $this->videoCountActive,
                    'total' => $this->videoCountTotal,
                ],
                'task' => [
                    'active' => $this->taskCountActive,
                    'total' => $this->taskCountTotal,
                    'byStatus' => $this->taskCountByStatus,
                    'willStartAt' => $this->willStartAt,
                ],
            ],
            'storage' => [
                'usedBytes' => $this->storageUsedBytes,
                'delete24Bytes' => $this->storageDelete24Bytes,
            ],
            'billing' => [
                'paidAt' => $this->paidAt?->format(DateTimeInterface::ATOM),
                'paidUntil' => $this->paidUntil?->format(DateTimeInterface::ATOM),
                'history' => $this->paymentHistory,
            ],
        ];

        if ($this->cf !== null) {
            $data['cf'] = $this->cf;
        }

        return $data;
    }

    /** Return a new instance with updated statistics, billing unchanged. */
    public function withStats(
        int $videoCountActive,
        int $videoCountTotal,
        int $taskCountActive,
        int $taskCountTotal,
        array $taskCountByStatus,
        int $storageUsedBytes,
        int $storageDelete24Bytes,
        string $willStartAt,
    ): self {
        return new self(
            videoCountActive: $videoCountActive,
            videoCountTotal: $videoCountTotal,
            taskCountActive: $taskCountActive,
            taskCountTotal: $taskCountTotal,
            taskCountByStatus: $taskCountByStatus,
            storageUsedBytes: $storageUsedBytes,
            storageDelete24Bytes: $storageDelete24Bytes,
            willStartAt: $willStartAt,
            paidAt: $this->paidAt,
            paidUntil: $this->paidUntil,
            paymentHistory: $this->paymentHistory,
            cf: $this->cf,
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Return a new instance with updated billing, statistics unchanged. */
    public function withBilling(
        ?DateTimeImmutable $paidAt,
        ?DateTimeImmutable $paidUntil,
        array $paymentHistory,
    ): self {
        return new self(
            videoCountActive: $this->videoCountActive,
            videoCountTotal: $this->videoCountTotal,
            taskCountActive: $this->taskCountActive,
            taskCountTotal: $this->taskCountTotal,
            taskCountByStatus: $this->taskCountByStatus,
            storageUsedBytes: $this->storageUsedBytes,
            storageDelete24Bytes: $this->storageDelete24Bytes,
            willStartAt: $this->willStartAt,
            paidAt: $paidAt,
            paidUntil: $paidUntil,
            paymentHistory: $paymentHistory,
            cf: $this->cf,
        );
    }

    /** Return a new instance with updated encrypted CF blob, all other fields unchanged. */
    public function withCf(?string $cf): self
    {
        return new self(
            videoCountActive: $this->videoCountActive,
            videoCountTotal: $this->videoCountTotal,
            taskCountActive: $this->taskCountActive,
            taskCountTotal: $this->taskCountTotal,
            taskCountByStatus: $this->taskCountByStatus,
            storageUsedBytes: $this->storageUsedBytes,
            storageDelete24Bytes: $this->storageDelete24Bytes,
            willStartAt: $this->willStartAt,
            paidAt: $this->paidAt,
            paidUntil: $this->paidUntil,
            paymentHistory: $this->paymentHistory,
            cf: $cf,
        );
    }

    public function isSubscriptionActive(): bool
    {
        if ($this->paidUntil === null) {
            return false;
        }

        return $this->paidUntil >= new DateTimeImmutable();
    }
}
