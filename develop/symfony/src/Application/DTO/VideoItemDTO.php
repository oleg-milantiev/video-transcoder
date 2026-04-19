<?php
declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Helper\HumanReadableHelper;
use App\Domain\User\Entity\Tariff;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;

readonly class VideoItemDTO
{
    private function __construct(
        public string $uuid,
        public string $title,
        public string $createdAt,
        public string $updatedAt,
        public ?string $expiredAt,
        public ?string $expiredInterval,
        public bool $deleted,
        public bool $canBeDeleted,
        public array $meta,
        public ?string $poster = null,
    ) {}

    public static function fromDomain(Video $video, StorageInterface $storage, TaskRepositoryInterface $taskRepository, ?Tariff $tariff = null): self
    {
        $hasPreview = ($video->meta()['preview'] ?? false) === true;
        $poster = $hasPreview ? $storage->publicUrl($storage->previewKey($video)) : null;

        if ($tariff) {
            $expiredAt = $video->createdAt()->add(new \DateInterval('PT' . $tariff->storageHour()->value() . 'H'));
        }

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
            updatedAt: $video->updatedAt()?->format(\DateTimeInterface::ATOM) ?? '',
            expiredAt: $tariff ? $expiredAt->format(\DateTimeInterface::ATOM) : null,
            expiredInterval: $tariff ? HumanReadableHelper::formatDateExpired($expiredAt) : null,
            deleted: $video->isDeleted(),
            canBeDeleted: $canBeDeleted,
            meta: self::decorateMeta($video->meta()),
            poster: $poster,
        );
    }

    private static function decorateMeta(array $meta): array
    {
        unset($meta['preview']);
        unset($meta['sourceKey']);

        // todo на frontend убрать width и height, не тут
//        if (isset($meta['width'], $meta['height'])) {
//            $meta['resolution'] = sprintf('%dx%d', $meta['width'], $meta['height']);
//            unset($meta['width'], $meta['height']);
//        }
        if (isset($meta['duration'])) {
            $meta['duration'] = HumanReadableHelper::formatDuration($meta['duration']);
        }
        if (isset($meta['size'])) {
            $meta['size'] = HumanReadableHelper::formatFileSize($meta['size']);
        }
        if (isset($meta['bitrate'])) {
            $meta['bitrate'] = HumanReadableHelper::formatBitrate($meta['bitrate']);
        }

        return $meta;
    }
}
