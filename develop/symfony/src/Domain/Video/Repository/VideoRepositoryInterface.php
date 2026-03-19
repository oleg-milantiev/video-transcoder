<?php

namespace App\Domain\Video\Repository;

use App\Domain\Video\Entity\Video;


use Symfony\Component\Uid\Uuid;

interface VideoRepositoryInterface extends PaginatedRepositoryInterface
{
    public function save(Video $video): Video;
    public function findById(Uuid $id): ?Video;
    public function log(Uuid $id, string $level, string $text): void;

    /**
     * Get video details with all presets (sorted by title) and their tasks.
     *
     * @return array{video: Video, presetsWithTasks: array<array{id: int, title: string, task: ?array{id: int, status: int, progress: int, createdAt: string}}>}|null
     */
    public function getDetails(Video $video): array;
}
