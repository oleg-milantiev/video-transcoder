<?php

namespace App\Application\QueryHandler;

use App\Application\Query\GetVideoListQuery;
use App\Application\Response\VideoListResponse;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetVideoListHandler
{
    public function __construct(
        private VideoRepositoryInterface $repository,
    ) {
    }

    public function __invoke(GetVideoListQuery $query): VideoListResponse
    {
        $result = $this->repository->findAllPaginated($query->page, $query->limit);

        return VideoListResponse::fromDomain(
            $result->items,
            $result->total,
            $query->page,
            $query->limit
        );
    }
}
