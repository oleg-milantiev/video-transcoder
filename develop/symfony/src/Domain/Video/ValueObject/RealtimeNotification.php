<?php
declare(strict_types=1);

namespace App\Domain\Video\ValueObject;

use App\Domain\Video\Exception\InvalidRealtimeNotification;

final readonly class RealtimeNotification
{
    private const int MIN_TIMER_MS = 0;
    private const int MAX_TIMER_MS = 60_000;
    private const int MAX_TITLE_LENGTH = 140;

    private function __construct(
        private RealtimeNotificationLevel $level,
        private string $title,
        private string $html,
        private int $timerMs,
        private RealtimeNotificationPosition $position,
        private ?string $imageUrl,
        private ?string $imageAlt,
    ) {
    }

    public static function create(
        RealtimeNotificationLevel $level,
        string $title,
        string $html,
        int $timerMs = 5000,
        RealtimeNotificationPosition $position = RealtimeNotificationPosition::TOP_END,
        ?string $imageUrl = null,
        ?string $imageAlt = null,
    ): self {
        $normalizedTitle = trim($title);
        $normalizedHtml = trim($html);

        if ($normalizedTitle == '') {
            throw InvalidRealtimeNotification::titleEmpty();
        }

        if (mb_strlen($normalizedTitle) > self::MAX_TITLE_LENGTH) {
            throw InvalidRealtimeNotification::titleTooLong(self::MAX_TITLE_LENGTH);
        }

        if ($normalizedHtml == '') {
            throw InvalidRealtimeNotification::htmlEmpty();
        }

        if ($timerMs < self::MIN_TIMER_MS || $timerMs > self::MAX_TIMER_MS) {
            throw InvalidRealtimeNotification::timerOutOfRange($timerMs, self::MIN_TIMER_MS, self::MAX_TIMER_MS);
        }

        $normalizedImageUrl = self::normalizeImageUrl($imageUrl);
        $normalizedImageAlt = self::normalizeImageAlt($imageAlt, $normalizedTitle, $normalizedImageUrl !== null);

        return new self(
            level: $level,
            title: $normalizedTitle,
            html: $normalizedHtml,
            timerMs: $timerMs,
            position: $position,
            imageUrl: $normalizedImageUrl,
            imageAlt: $normalizedImageAlt,
        );
    }

    public function level(): RealtimeNotificationLevel
    {
        return $this->level;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function html(): string
    {
        return $this->html;
    }

    public function timerMs(): int
    {
        return $this->timerMs;
    }

    public function position(): RealtimeNotificationPosition
    {
        return $this->position;
    }

    public function imageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function imageAlt(): ?string
    {
        return $this->imageAlt;
    }

    private static function normalizeImageUrl(?string $imageUrl): ?string
    {
        if ($imageUrl === null) {
            return null;
        }

        $trimmed = trim($imageUrl);
        if ($trimmed === '') {
            return null;
        }

        $isAbsoluteUrl = filter_var($trimmed, FILTER_VALIDATE_URL) !== false;
        $isAbsolutePath = str_starts_with($trimmed, '/');
        if (!$isAbsoluteUrl && !$isAbsolutePath) {
            throw InvalidRealtimeNotification::invalidImageUrl($trimmed);
        }

        return $trimmed;
    }

    private static function normalizeImageAlt(?string $imageAlt, string $title, bool $hasImage): ?string
    {
        if (!$hasImage) {
            return null;
        }

        $trimmed = trim((string) $imageAlt);
        if ($trimmed !== '') {
            return $trimmed;
        }

        return $title;
    }
}
