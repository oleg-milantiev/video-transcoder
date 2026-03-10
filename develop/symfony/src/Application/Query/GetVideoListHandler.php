<?php

namespace App\Application\Query;

use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Application\DTO\VideoListResponse;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class GetVideoListHandler {
    public function __construct(
        private VideoRepositoryInterface $videoRepository
    ) {}

    public function __invoke(GetVideoListQuery $query): VideoListResponse
    {
        $result = $this->videoRepository->findAllPaginated($query->page, $query->limit);

        return VideoListResponse::fromDomain(
            $result->items,
            $result->total,
            $query->page,
            $query->limit
        );
    }
}
