<?php

namespace App\Application\Query;

use App\Application\Response\TaskListResponse;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetTaskListHandler
{
    public function __construct(
        private TaskRepositoryInterface $repository,
    ) {
    }

    public function __invoke(GetTaskListQuery $query): TaskListResponse
    {
        $result = $this->repository->findAllPaginated($query->page, $query->limit);

        return TaskListResponse::fromDomain(
            $result->items,
            $result->total,
            $query->page,
            $query->limit
        );
    }
}
