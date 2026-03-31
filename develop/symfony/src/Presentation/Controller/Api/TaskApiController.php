<?php

namespace App\Presentation\Controller\Api;

use App\Application\Exception\InvalidUuidException;
use App\Application\Exception\QueryException;
use App\Application\Exception\TaskCancelAccessDeniedException;
use App\Application\Exception\TaskNotFoundException;
use App\Application\Exception\VideoNotFoundException;
use App\Application\Query\TaskCancelQuery;
use App\Application\Logging\LogServiceInterface;
use Psr\Log\LoggerInterface;
use App\Application\Query\GetTaskListQuery;
use App\Application\QueryHandler\QueryBus;
use App\Application\Response\TaskListResponse;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/task')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class TaskApiController extends AbstractController
{
    use ApiJsonResponseTrait;

    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly TaskRepositoryInterface $taskRepository,
        private readonly VideoRepositoryInterface $videoRepository,
        private readonly LogServiceInterface $logService,
        private readonly LoggerInterface $logger,
        private readonly Security $security,
    ) {
    }

    #[Route('/', name: 'api_task_list', methods: ['GET'])]
    public function index(Request $request): Response
    {
        try {
            /** @var TaskListResponse $taskListResponse */
            $taskListResponse = $this->queryBus->query(
                new GetTaskListQuery($request, Uuid::fromString($this->getUser()->id->toRfc4122()))
            );

            return $this->apiSuccess((array) $taskListResponse);
        } catch (QueryException $e) {
            return $this->apiError('QUERY_FAILED', $e->getMessage(), 400);
        } catch (\Throwable $e) {
            $this->logger->critical('Failed to list tasks', ['exception' => $e]);
            return $this->apiError('INTERNAL_ERROR', 'Failed to list tasks', 500);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/{id}/cancel', name: 'api_task_cancel', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['POST'])]
    public function cancel(string $id): Response
    {
        try {
            // todo DTO
            $result = $this->queryBus->query(new TaskCancelQuery($id, $this->getUser()->id->toRfc4122()));

            return $this->apiSuccess((array) $result);
        } catch (HandlerFailedException $e) {
            // todo образец. Размножить на остальные API
            $return = match ($e->getPrevious()::class) {
                // todo рефакторинг папок application/exception
                InvalidUuidException::class => $this->apiError('INVALID_TASK_ID', $e->getPrevious()->getMessage(), 400),
                TaskNotFoundException::class => $this->apiError('TASK_NOT_FOUND', $e->getPrevious()->getMessage(), 404),
                VideoNotFoundException::class => $this->apiError('VIDEO_NOT_FOUND', $e->getPrevious()->getMessage(), 404),
                TaskCancelAccessDeniedException::class => $this->apiError('ACCESS_DENIED', $e->getPrevious()->getMessage(), 403),
                default => $this->apiError('INTERNAL_ERROR', 'Failed to cancel task: '. get_class($e->getPrevious()), 500),
            };

            if ($return->getStatusCode() === 500) {
                $this->logger->critical('Failed to cancel task', ['exception' => $e]);
            }

            return $return;
        } catch (\Throwable $e) {
            $this->logger->critical('Failed to cancel task', ['exception' => $e]);
            return $this->apiError('INTERNAL_ERROR', 'Failed to cancel task', 500);
        }
    }
}
