<?php
declare(strict_types=1);

namespace App\Presentation\Controller\Api;

use App\Application\Exception\InvalidUuidException;
use App\Application\Exception\QueryException;
use App\Application\Exception\TaskCancelAccessDeniedException;
use App\Application\Exception\TaskNotFoundException;
use App\Application\Exception\VideoNotFoundException;
use App\Application\Query\TaskCancelQuery;
use App\Application\Logging\LogServiceInterface;
use App\Application\Query\GetTaskListQuery;
use App\Application\QueryHandler\QueryBus;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        private readonly Security $security,
    ) {
    }

    #[Route('/', name: 'api_task_list', methods: ['GET'])]
    public function index(Request $request): Response
    {
        try {
            return $this->apiSuccess((array)
                $this->queryBus->query(
                    new GetTaskListQuery($request, Uuid::fromString($this->getUser()->id->toRfc4122()))
                )
            );
        } catch (QueryException $e) {
            // todo many user-friendly errors
            return $this->apiError('QUERY_FAILED', $e->getMessage(), 400);
        } catch (\Throwable $e) {
            $this->logService->log('task', 'index', null, LogLevel::CRITICAL, 'Fail', [
                'message' => $e->getMessage(),
            ]);
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
        } catch (InvalidUuidException $e) {
            return $this->apiError('INVALID_TASK_ID', $e->getMessage(), 400);
        } catch (TaskNotFoundException $e) {
            return $this->apiError('TASK_NOT_FOUND', $e->getMessage(), 404);
        } catch (VideoNotFoundException $e) {
            return $this->apiError('VIDEO_NOT_FOUND', $e->getMessage(), 404);
        } catch (TaskCancelAccessDeniedException $e) {
            return $this->apiError('ACCESS_DENIED', $e->getMessage(), 403);
        } catch (\Throwable $e ) {
            $this->logService->log('task', 'cancel', Uuid::fromStringNullable($id), LogLevel::CRITICAL, 'Fail', [
                'id' => $id,
                'message' => $e->getMessage(),
            ]);

            return $this->apiError('INTERNAL_ERROR', 'Failed to cancel task', 500);
        }
    }
}
