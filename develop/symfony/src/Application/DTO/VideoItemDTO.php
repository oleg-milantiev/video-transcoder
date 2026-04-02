<?php
declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;

readonly class VideoItemDTO
{
    private function __construct(
        public string $uuid,
        public string $title,
        public string $createdAt,
        public bool $deleted,
        public bool $canBeDeleted,
        public ?string $poster = null,
    ) {}

    public static function fromDomain(Video $video, StorageInterface $storage, TaskRepositoryInterface $taskRepository): self
    {
        $hasPreview = ($video->meta()['preview'] ?? false) === true;
        $poster = $hasPreview ? $storage->publicUrl($storage->previewKey($video)) : null;

        $canBeDeleted = true;
        foreach ($taskRepository->findByVideoId($video->id()) as $task) {
            if (!$task->status()->canBeDeleted()) {
                $canBeDeleted = false;
                break;
            }
        }

        return new self(
            uuid: $video->id()?->toRfc4122() ?? '',
            title: $video->title()->value(),
            createdAt: $video->createdAt()->format(\DateTimeInterface::ATOM),
            deleted: $video->isDeleted(),
            canBeDeleted: $canBeDeleted,
            poster: $poster,
        );
    }
}
