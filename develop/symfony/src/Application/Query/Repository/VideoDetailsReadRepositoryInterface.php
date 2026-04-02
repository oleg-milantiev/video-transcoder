<?php
declare(strict_types=1);

namespace App\Application\Query\Repository;

use App\Domain\Shared\ValueObject\Uuid;

interface VideoDetailsReadRepositoryInterface
{
    /**
     * @return array<array{
     *     id: string,
     *     title: string,
     *     expectedFileSize: int,
     *     task: ?array{
     *         id: string,
     *         status: int,
     *         progress: int,
     *         createdAt: string,
     *         downloadFilename: string,
     *         waitingTariffInstance: ?bool,
     *         waitingTariffDelay: ?bool,
     *         willStartAt: ?string
     *     }
     * }>
     *
     * Date fields are expected to be serialized as ISO 8601 / ATOM strings in UTC.
     */
    public function getDetailsByVideoId(Uuid $videoId): array;
}
