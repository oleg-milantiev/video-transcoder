<?php

namespace App\Presentation\Controller;

use App\Application\Exception\QueryException;
use App\Application\Query\GetTaskListQuery;
use App\Application\QueryHandler\QueryBus;
use App\Application\Response\TaskListResponse;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\TaskStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/task')]
class TaskController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly TaskRepositoryInterface $taskRepository,
        private readonly StorageInterface $storage,
    ) {
    }

    #[Route('/', name: 'task')]
    public function index(Request $request): Response
    {
        try {
            /** @var TaskListResponse $taskListResponse */
            $taskListResponse = $this->queryBus->query(
                new GetTaskListQuery($request)
            );

            // TODO use all tasks list data and paged api call in dataTable
            return new JsonResponse($taskListResponse->items);
        } catch (QueryException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}/download', name: 'task_download', requirements: ['id' => '\\d+'])]
    public function download(int $id): Response
    {
        $task = $this->taskRepository->findById($id);
        if (!$task) {
            throw $this->createNotFoundException('Task not found');
        }

        // TODO voter with admin grants
        if ($task->userId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException('Access denied');
        }

        if ($task->status() !== TaskStatus::COMPLETED) {
            throw $this->createNotFoundException('Task output is not ready');
        }

        $output = $task->meta()['output'] ?? null;
        if (!$output) {
            throw $this->createNotFoundException('Output file not found');
        }

        return $this->redirect($this->storage->getUrl($output));
    }
}
