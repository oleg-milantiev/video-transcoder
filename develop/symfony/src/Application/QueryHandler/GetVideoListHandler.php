<?php

namespace App\Application\QueryHandler;

use App\Application\Query\GetVideoListQuery;
use App\Application\Response\VideoListResponse;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class GetVideoListHandler
{
    public function __construct(
        private VideoRepositoryInterface $videoRepository,
        private StorageInterface $storage,
    ) {
    }

    public function __invoke(GetVideoListQuery $query): VideoListResponse
    {
        $result = $this->videoRepository->findAllPaginated($query->page, $query->limit);

        return VideoListResponse::fromDomain(
            $result->items,
            $result->total,
            $query->page,
            $query->limit,
            $this->storage,
        );
    }
}
