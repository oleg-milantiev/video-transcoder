<?php
declare(strict_types=1);

namespace App\Application\QueryHandler;

use App\Application\Command\Video\CleanupDeletedVideoMedia;
use App\Application\Event\DeleteVideoFail;
use App\Application\Event\DeleteVideoStart;
use App\Application\Event\DeleteVideoSuccess;
use App\Application\Exception\TranscodeAccessDeniedException;
use App\Application\Exception\VideoNotFoundException;
use App\Application\Logging\LogServiceInterface;
use App\Application\Query\DeleteTaskQuery;
use App\Application\Query\DeleteVideoQuery;
use App\Application\Service\Storage\StorageRealtimeNotifierInterface;
use App\Application\Service\Video\VideoRealtimeNotifier;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Infrastructure\Security\Voter\VideoAccessVoter;
use Psr\Log\LogLevel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class DeleteVideoHandler
{
    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
        #[Autowire(service: 'messenger.bus.event')]
        private MessageBusInterface $eventBus,
        private VideoRepositoryInterface $videoRepository,
        private TaskRepositoryInterface $taskRepository,
        private LogServiceInterface $logService,
        private VideoRealtimeNotifier $videoRealtimeNotifier,
        private Security $security,
        private QueryBus $queryBus,
        private StorageRealtimeNotifierInterface $storageNotifier,
    ) {
    }

    public function __invoke(DeleteVideoQuery $query): void
    {
        $this->eventBus->dispatch(new DeleteVideoStart(
            videoId: $query->videoId->toRfc4122(),
            requestedByUserId: $query->requestedByUserId->toRfc4122(),
        ));

        $video = $this->videoRepository->findById($query->videoId);
        if ($video === null) {
            $this->eventBus->dispatch(new DeleteVideoFail(
                error: 'Video not found',
                videoId: $query->videoId->toRfc4122(),
                requestedByUserId: $query->requestedByUserId->toRfc4122(),
            ));

            throw new VideoNotFoundException('Video not found');
        }

        if (!$this->security->isGranted(VideoAccessVoter::CAN_DELETE, $video)) {
            $this->eventBus->dispatch(new DeleteVideoFail(
                error: 'Access denied',
                videoId: $query->videoId->toRfc4122(),
                requestedByUserId: $query->requestedByUserId->toRfc4122(),
            ));

            throw new TranscodeAccessDeniedException('Access denied');
        }

        try {
            if ($video->id() === null) {
                throw new \RuntimeException('Video id is required for deletion.');
            }

            $tasks = $this->taskRepository->findByVideoId($video->id());
            $video->markDeleted($tasks);

            $deletedTaskCount = 0;
            foreach ($tasks as $task) {
                if ($task->isDeleted()) {
                    continue;
                }

                if ($task->id() === null) {
                    throw new \RuntimeException('Task id is required for deletion.');
                }

                $this->queryBus->query(new DeleteTaskQuery(
                    $task->id()->toRfc4122(),
                    $query->requestedByUserId->toRfc4122(),
                ));
                $deletedTaskCount++;
            }

            $this->videoRepository->save($video);

            $context = [
                'videoId' => $video->id()?->toRfc4122(),
                'requestedByUserId' => $query->requestedByUserId->toRfc4122(),
                'deletedTaskCount' => $deletedTaskCount,
            ];
            $this->logService->log('video', 'delete', $video->id(), LogLevel::INFO, 'Video marked as deleted', $context);
            $this->videoRealtimeNotifier->notifyVideoUpdated($video, 'deleted', [
                'deleted' => true,
            ]);
            $this->storageNotifier->notifyStorageUpdated($video->userId());

            $this->commandBus->dispatch(new CleanupDeletedVideoMedia($video->id()));

            $this->eventBus->dispatch(new DeleteVideoSuccess(
                videoId: $video->id()->toRfc4122(),
                requestedByUserId: $query->requestedByUserId->toRfc4122(),
                deletedTaskCount: $deletedTaskCount,
            ));
        } catch (\Throwable $e) {
            $this->eventBus->dispatch(new DeleteVideoFail(
                error: $e->getMessage(),
                videoId: $query->videoId->toRfc4122(),
                requestedByUserId: $query->requestedByUserId->toRfc4122(),
            ));

            throw $e;
        }
    }
}
