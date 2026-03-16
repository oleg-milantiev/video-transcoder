<?php

namespace App\Domain\Video\Repository;

use App\Domain\Video\Entity\Video;


use Symfony\Component\Uid\Uuid;

interface VideoRepositoryInterface extends PaginatedRepositoryInterface
{
    public function save(Video $video): void;
    public function findById(int $id): ?Video;

    /**
     * Добавляет запись в лог видео по uuid.
     * @param Uuid $id
     * @param string $level
     * @param string $text
     */
    public function log(Uuid $id, string $level, string $text): void;
}
