<?php
declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Video\ValueObject\RealtimeNotification;

final readonly class FlashNotificationDTO
{
    public function __construct(
        public string $level,
        public string $type,
        public string $title,
        public string $html,
        public int $timer,
        public string $position,
        public ?string $imageUrl = null,
        public ?string $imageAlt = null,
    ) {
    }

    public static function fromDomain(RealtimeNotification $notification): self
    {
        return new self(
            level: $notification->level()->value,
            type: $notification->level()->value,
            title: $notification->title(),
            html: $notification->html(),
            timer: $notification->timerMs(),
            position: $notification->position()->value,
            imageUrl: $notification->imageUrl(),
            imageAlt: $notification->imageAlt(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'level' => $this->level,
            'type' => $this->type,
            'title' => $this->title,
            'html' => $this->html,
            'timer' => $this->timer,
            'position' => $this->position,
            'imageUrl' => $this->imageUrl,
            'imageAlt' => $this->imageAlt,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
