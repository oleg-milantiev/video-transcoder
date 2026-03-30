<?php

namespace App\Application\DTO;

use App\Domain\User\Entity\Tariff;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Service\Storage\StorageInterface;

readonly class VideoDetailsDTO
{
    public function __construct(
        public string $id,
        public string $title,
        public string $extension,
        public string $createdAt,
        public ?string $updatedAt,
        public string $expiredAt,
        public string $expiredInterval,
        public array $meta,
        public ?string $poster,
        public bool $deleted = false,
        /** @var PresetWithTaskDTO[] */
        public array $presetsWithTasks = [],
    ) {}

    public static function fromDomain(Video $video, array $presetsWithTasks, StorageInterface $storage, Tariff $tariff): self
    {
        $hasPreview = ($video->meta()['preview'] ?? false) === true;
        $expiredAt = $video->createdAt()->add(new \DateInterval('PT' . $tariff->storageHour()->value() . 'H'));
        $expiredInterval = $expiredAt->diff(new \DateTimeImmutable());

        return new self(
            id: $video->id()->toRfc4122(),
            title: $video->title()->value(),
            extension: $video->extension()->value(),
            createdAt: $video->createdAt()->format('Y-m-d H:i'),
            updatedAt: $video->updatedAt()?->format('Y-m-d H:i'),
            expiredAt: $expiredAt->format('Y-m-d H:i'),
            expiredInterval: $expiredAt > new \DateTimeImmutable() ? $expiredInterval->format('in %a days %h hours') : 'expired',
            meta: self::sanitizeMeta($video->meta()),
            poster: $hasPreview ? $storage->publicUrl($storage->previewKey($video)) : null,
            deleted: $video->isDeleted(),
            presetsWithTasks: $presetsWithTasks,
        );
    }

    protected static function sanitizeMeta(array $meta): array
    {
        unset($meta['preview']);

        return $meta;
    }
}
