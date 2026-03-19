<?php

namespace App\Domain\Video\Entity;

use App\Application\Command\Video\CreateVideo;
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
    private int $userId;
    private array $meta;

    public function __construct(
        VideoTitle $title,
        FileExtension $extension,
        VideoStatus $status,
        // TODO DDD VO
        int $userId,
        array $meta = [],
        ?VideoDates $dates = null,
        ?Uuid $id = null,
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->extension = $extension;
        $this->status = $status;
        $this->dates = $dates ?? VideoDates::create();
        $this->userId = $userId;
        $this->meta = $meta;
    }

    public static function create(
        VideoTitle $title,
        FileExtension $extension,
        VideoStatus $status,
        int $userId,
        array $meta = [],
        ?VideoDates $dates = null,
        ?Uuid $id = null,
    ): self {
        return new self($title, $extension, $status, $userId, $meta, $dates, $id);
    }

    public static function createFromCommand(CreateVideo $command): self
    {
        return self::create(
            title: new VideoTitle($command->file()->details()['metadata']['originalName'] ?? $command->file()->getName()),
            extension: new FileExtension(pathinfo($command->file()->getName(), PATHINFO_EXTENSION)),
            status: VideoStatus::UPLOADED,
            userId: $command->userId(),
        );
    }

    public function generateId(): void
    {
        $this->id = Uuid::v4();
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

    public function userId(): int
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
