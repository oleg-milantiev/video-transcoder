<?php

namespace App\Domain\Video\Entity;

use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoStatus;
use App\Domain\Video\ValueObject\VideoTitle;
use Symfony\Component\Uid\UuidV4 as Uuid;

class Video
{
    private ?Uuid $id;
    private VideoTitle $title;
    private FileExtension $extension;
    private VideoStatus $status;
    private VideoDates $dates;
    private Uuid $userId;
    private array $meta;

    private function __construct(
        VideoTitle $title,
        FileExtension $extension,
        VideoStatus $status,
        Uuid $userId,
        array $meta,
        VideoDates $dates,
        ?Uuid $id,
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->extension = $extension;
        $this->status = $status;
        $this->dates = $dates;
        $this->userId = $userId;
        $this->meta = $meta;
    }

    public static function create(
        VideoTitle $title,
        FileExtension $extension,
        VideoStatus $status,
        Uuid $userId,
        array $meta = [],
    ): self {
        return new self($title, $extension, $status, $userId, $meta, VideoDates::create(), null);
    }

    public static function reconstitute(
        VideoTitle $title,
        FileExtension $extension,
        VideoStatus $status,
        Uuid $userId,
        array $meta,
        VideoDates $dates,
        Uuid $id,
    ): self {
        return new self($title, $extension, $status, $userId, $meta, $dates, $id);
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

    public function status(): VideoStatus
    {
        return $this->status;
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
        $this->meta = array_merge($this->meta, $meta);
        $this->dates = $this->dates->touch();
    }

    public function duration(): ?float
    {
        return $this->meta['duration'] ?? null;
    }

    public function getSrcFilename(): string
    {
        return $this->id->toString() . '.' . $this->extension->value();
    }

    public function getPoster(): ?string
    {
        if (($this->meta['preview'] ?? false) === true && $this->id) {
            return $this->id->toString() . '.jpg';
        }
        return null;
    }
}
