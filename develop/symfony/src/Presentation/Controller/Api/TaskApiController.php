<?php

namespace App\Presentation\Controller\Api;

use App\Application\Exception\QueryException;
use Psr\Log\LogLevel;
use App\Application\Logging\LogServiceInterface;
use App\Application\Query\GetTaskListQuery;
use App\Application\QueryHandler\QueryBus;
use App\Application\Response\TaskListResponse;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Infrastructure\Security\Voter\VideoAccessVoter;
use App\Infrastructure\Task\TaskCancellationTrigger;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\UuidV4;

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
        private readonly TaskCancellationTrigger $cancellationTrigger,
    ) {
    }

    #[Route('/', name: 'api_task_list', methods: ['GET'])]
    public function index(Request $request): Response
    {
        try {
            /** @var TaskListResponse $taskListResponse */
            $taskListResponse = $this->queryBus->query(
                new GetTaskListQuery($request)
            );

            return new JsonResponse($taskListResponse);
        } catch (QueryException $e) {
            // TODO тут не $this->apiError?
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/{id}/cancel', name: 'api_task_cancel', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['POST'])]
    public function cancel(string $id): Response
    {
        try {
            $taskId = UuidV4::fromString($id);
        } catch (\Throwable) {
            return $this->apiError('INVALID_TASK_ID', 'Invalid task id', 400);
        }

        $task = $this->taskRepository->findById($taskId);
        if (!$task) {
            return $this->apiError('TASK_NOT_FOUND', 'Task not found', 404);
        }

        $video = $this->videoRepository->findById($task->videoId());
        if (!$video) {
            return $this->apiError('VIDEO_NOT_FOUND', 'Video not found', 404);
        }

        if (!$this->security->isGranted(VideoAccessVoter::CAN_CANCEL_TRANSCODE, $video)) {
            return $this->apiError('ACCESS_DENIED', 'Access denied', 403);
        }

        // TODO atomize it!
        // via $lock = $this->lockFactory->createLock(sprintf('transcode-task:%d', $scheduledTask->taskId), self::TASK_MUTEX_TTL);
        // not db task status
        $task->updateMeta([
            'cancelledByUserId' => $this->getUser()->id->toRfc4122(),
            'cancelRequestedAt' => new \DateTimeImmutable()->format(DATE_ATOM),
        ]);

        $cancelledNow = $task->status() === TaskStatus::PENDING && $task->startedAt() === null;
        $requestedByUserId = $this->getUser()->id;

        if ($cancelledNow) {
            $task->cancel();
            $this->logService->log('task', $task->id(), LogLevel::INFO, 'Task cancelled before start', [
                'videoId' => $video->id()?->toRfc4122(),
                'requestedByUserId' => $requestedByUserId?->toRfc4122(),
            ]);
        }
        else {
            $this->logService->log('task', $task->id(), LogLevel::INFO, 'Cancellation requested in progress', [
                'videoId' => $video->id()?->toRfc4122(),
                'requestedByUserId' => $requestedByUserId?->toRfc4122(),
            ]);
        }

        $this->logService->log('video', $video->id(), LogLevel::INFO, 'Cancellation requested for video task', [
            'taskId' => $task->id()->toRfc4122(),
            'requestedByUserId' => $requestedByUserId?->toRfc4122(),
            'cancelledNow' => $cancelledNow,
        ]);

        $this->logService->log('user', $requestedByUserId, LogLevel::INFO, 'User requested transcode cancellation', [
            'taskId' => $task->id()->toRfc4122(),
            'videoId' => $video->id()?->toRfc4122(),
            'cancelledNow' => $cancelledNow,
        ]);

        $this->taskRepository->save($task);

        $this->cancellationTrigger->request($task->id());

        return $this->apiSuccess([
            'task' => [
                'id' => $task->id()->toRfc4122(),
                'status' => $task->status()->name,
                'cancelledNow' => $cancelledNow,
                'cancellationRequested' => true,
            ],
        ]);
    }
}


