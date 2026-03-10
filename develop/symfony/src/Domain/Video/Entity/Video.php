<?php

namespace App\Domain\Video\Entity;

use App\Application\Command\Video\CreateVideo;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoStatus;
use App\Domain\Video\ValueObject\VideoTitle;
use Symfony\Component\Uid\Uuid;

class Video
{
    private ?Uuid $id = null;
    private VideoTitle $title;
    private FileExtension $extension;
    private VideoStatus $status;
    // TODO DDD
    private \DateTimeImmutable $createdAt;
    // TODO DDD
    private ?\DateTimeImmutable $updatedAt = null;
    private int $userId;
    private array $meta = [];

    public function __construct(
        VideoTitle $title,
        FileExtension $extension,
        VideoStatus $status,
        // TODO DDD VO
        int $userId,
        // TODO DDD VO
        ?\DateTimeImmutable $createdAt = null,
        // TODO DDD VO
        array $meta = [],
        ?string $id = null,
    ) {
        $this->id = $id ? Uuid::fromString($id) : Uuid::v4();

        $this->title = $title;
        $this->extension = $extension;
        $this->status = $status;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->userId = $userId;
        $this->meta = $meta;
    }

    public static function createFromCommand(CreateVideo $command): self
    {
        return new self(
            title: new VideoTitle($command->file()->getName()),
            extension: new FileExtension(pathinfo($command->file()->getName(), PATHINFO_EXTENSION)),
            status: VideoStatus::UPLOADED,
            userId: $command->userId(),
        );
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
        return $this->createdAt;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
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
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function duration(): ?float
    {
        return $this->meta['duration'] ?? null;
    }

    public function getSrcFilename(): string
    {
        return $this->id->toString() . DIRECTORY_SEPARATOR . 'src.' . $this->extension->value();
    }
}
