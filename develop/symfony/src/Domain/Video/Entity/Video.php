<?php
declare(strict_types=1);

namespace App\Domain\Video\Entity;

use App\Domain\Video\Exception\VideoAlreadyDeleted;
use App\Domain\Video\Exception\VideoHasTranscodingTasks;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoTitle;
use App\Domain\Shared\ValueObject\Uuid;

class Video
{
    private ?Uuid $id;
    private VideoTitle $title;
    private FileExtension $extension;
    private VideoDates $dates;
    private Uuid $userId;
    private array $meta;
    private bool $deleted;

    private function __construct(
        VideoTitle $title,
        FileExtension $extension,
        Uuid $userId,
        array $meta,
        bool $deleted,
        VideoDates $dates,
        ?Uuid $id,
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->extension = $extension;
        $this->dates = $dates;
        $this->userId = $userId;
        $this->meta = $meta;
        $this->deleted = $deleted;
    }

    public static function create(
        VideoTitle $title,
        FileExtension $extension,
        Uuid $userId,
        array $meta = [],
    ): self {
        return new self($title, $extension, $userId, $meta, false, VideoDates::create(), null);
    }

    public static function reconstitute(
        VideoTitle $title,
        FileExtension $extension,
        Uuid $userId,
        array $meta,
        VideoDates $dates,
        Uuid $id,
        bool $deleted = false,
    ): self {
        return new self($title, $extension, $userId, $meta, $deleted, $dates, $id);
    }

    public function id(): ?Uuid
    {
        return $this->id;
    }

    public function title(): VideoTitle
    {
        return $this->title;
    }

    public function extension(): FileExtension
    {
        return $this->extension;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->dates->createdAt();
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->dates->updatedAt();
    }

    public function userId(): Uuid
    {
        return $this->userId;
    }

    public function meta(): array
    {
        return $this->meta;
    }

    public function updateMeta(array $meta): void
    {
        $this->assertNotDeleted();
        $this->meta = array_merge($this->meta, $meta);
        $this->dates = $this->dates->touch();
    }

    /**
     * @param array<int, Task> $tasks
     */
    public function markDeleted(array $tasks): void
    {
        if ($this->deleted) {
            throw VideoAlreadyDeleted::forVideo();
        }

        foreach ($tasks as $task) {
            if (!$task->isDeleted() && $task->status()->isTranscoding()) {
                throw VideoHasTranscodingTasks::forVideo();
            }
        }

        $this->deleted = true;
        $this->dates = $this->dates->touch();
    }

    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    public function duration(): ?float
    {
        return $this->meta['duration'] ?? null;
    }

    public function size(): ?int
    {
        return $this->meta['size'] ?? null;
    }

    public function clearSourceKey(): void
    {
        $this->meta['sourceKey'] = null;
        $this->dates = $this->dates->touch();
    }

    public function changeTitle(VideoTitle $title): void
    {
        $this->assertNotDeleted();
        $this->title = $title;
        $this->dates = $this->dates->touch();
    }

    private function assertNotDeleted(): void
    {
        if ($this->deleted) {
            throw VideoAlreadyDeleted::forVideo();
        }
    }
}
