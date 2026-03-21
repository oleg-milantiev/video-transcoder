<?php

namespace App\Application\Query\Repository;

use Symfony\Component\Uid\UuidV4;

interface VideoDetailsReadRepositoryInterface
{
    /**
     * @return array<array{id: string, title: string, task: ?array{id: string, status: int, progress: int, createdAt: string}}>
     */
    public function getDetailsByVideoId(UuidV4 $videoId): array;
}
