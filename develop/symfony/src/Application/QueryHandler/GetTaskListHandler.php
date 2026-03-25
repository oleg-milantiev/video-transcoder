<?php

namespace App\Application\QueryHandler;

use App\Application\Query\GetTaskListQuery;
use App\Application\Response\TaskListResponse;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class GetTaskListHandler
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private VideoRepositoryInterface $videoRepository,
        private PresetRepositoryInterface $presetRepository,
    ) {
    }

    public function __invoke(GetTaskListQuery $query): TaskListResponse
    {
        $result = $this->taskRepository->findAllPaginated($query->page, $query->limit, $query->userId);

        $combinedItems = [];
        /** @var Task $task */
        foreach ($result->items as $task) {
            $combinedItems[] = [
                'task' => $task,
                'video' => $this->videoRepository->findById($task->videoId()),
                'preset' => $this->presetRepository->findById($task->presetId()),
            ];
        }

        return TaskListResponse::fromDomain(
            $combinedItems,
            $result->total,
            $query->page,
            $query->limit
        );
    }
}
