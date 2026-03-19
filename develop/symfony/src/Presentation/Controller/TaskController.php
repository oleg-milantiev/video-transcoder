<?php

namespace App\Presentation\Controller;

use App\Application\Exception\QueryException;
use App\Application\Query\GetTaskListQuery;
use App\Application\QueryHandler\QueryBus;
use App\Application\Response\TaskListResponse;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
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

#[Route('/task')]
class TaskController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly TaskRepositoryInterface $taskRepository,
        private readonly VideoRepositoryInterface $videoRepository,
        private readonly StorageInterface $storage,
        private readonly Security $security,
        private readonly TaskCancellationTrigger $cancellationTrigger,
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

    private function getTask(int $id): Task
    {
        $task = $this->taskRepository->findById($id);
        if (!$task) {
            throw $this->createNotFoundException('Task not found');
        }

        $video = $this->videoRepository->findById($task->videoId());
        if (!$video) {
            throw $this->createNotFoundException('Video not found');
        }

        if (!$this->security->isGranted(VideoAccessVoter::CAN_DOWNLOAD_TRANSCODE, $video)) {
            throw $this->createAccessDeniedException('Access denied');
        }

        return $task;
    }

    #[Route('/{id}/download', name: 'task_download', requirements: ['id' => '\\d+'])]
    public function download(int $id): Response
    {
        $task = $this->getTask($id);

        if ($task->status() !== TaskStatus::COMPLETED) {
            throw $this->createNotFoundException('Task output is not ready');
        }

        $output = $task->meta()['output'] ?? null;
        if (!$output) {
            throw $this->createNotFoundException('Output file not found');
        }

        return $this->redirect($this->storage->getUrl($output));
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/{id}/cancel', name: 'task_cancel', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function cancel(int $id): Response
    {
        $task = $this->getTask($id);

        // TODO atomize it!
        // via $lock = $this->lockFactory->createLock(sprintf('transcode-task:%d', $scheduledTask->taskId), self::TASK_MUTEX_TTL);
        // not db task status
        $task->updateMeta([
            'cancelledByUserId' => $this->getUser()->id,
            'cancelRequestedAt' => new \DateTimeImmutable()->format(DATE_ATOM),
        ]);

        $cancelledNow = $task->status() === TaskStatus::PENDING && $task->startedAt() === null;
        if ($cancelledNow) {
            $task->cancel();
            $this->taskRepository->log($task->id(), 'info', 'Task cancelled before start');
        }

        $this->taskRepository->save($task);

        $this->cancellationTrigger->request($task->id());
        $this->taskRepository->log($task->id(), 'info', 'Cancellation requested by user');

        return new JsonResponse([
            'status' => $task->status()->name,
            'cancelledNow' => $cancelledNow,
            'cancellationRequested' => true,
        ]);
    }
}
