<?php

namespace App\Presentation\Controller;

use App\Application\DTO\TaskListResponse;
use App\Application\Exception\QueryException;
use App\Application\Query\GetTaskListQuery;
use App\Application\Query\QueryBus;
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
}
