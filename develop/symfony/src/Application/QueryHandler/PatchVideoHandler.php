<?php
declare(strict_types=1);

namespace App\Application\QueryHandler;

use App\Application\DTO\VideoItemDTO;
use App\Application\Event\PatchVideoFail;
use App\Application\Event\PatchVideoStart;
use App\Application\Event\PatchVideoSuccess;
use App\Application\Query\PatchVideoQuery;
use App\Application\Logging\LogServiceInterface;
use App\Application\Service\Video\VideoRealtimeNotifier;
use App\Application\Service\Task\TaskRealtimeNotifier;
use App\Application\Exception\VideoNotFoundException;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\VideoTitle;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Infrastructure\Security\Voter\VideoAccessVoter;
use Psr\Log\LogLevel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class PatchVideoHandler
{
    public function __construct(
        #[Autowire(service: 'messenger.bus.event')]
        private MessageBusInterface $eventBus,
        private VideoRepositoryInterface $videoRepository,
        private Security $security,
        private VideoRealtimeNotifier $videoRealtimeNotifier,
        private TaskRepositoryInterface $taskRepository,
        private TaskRealtimeNotifier $taskRealtimeNotifier,
        private LogServiceInterface $logService,
        private StorageInterface $storage,
    ) {}

    public function __invoke(PatchVideoQuery $query): VideoItemDTO
    {
        $this->eventBus->dispatch(new PatchVideoStart(
            videoId: $query->videoId->toRfc4122(),
            requestedByUserId: $query->requestedByUserId->toRfc4122(),
            title: $query->title,
        ));

        $video = $this->videoRepository->findById($query->videoId);
        if ($video === null) {
            throw new VideoNotFoundException('Video not found');
        }

        if (!$this->security->isGranted(VideoAccessVoter::CAN_EDIT, $video)) {
            throw new \DomainException('Access denied');
        }

        try {
            $wasTitle = $video->title()->value();
            $video->changeTitle(new VideoTitle($query->title));
            $this->videoRepository->save($video);
        } catch (\Throwable $e) {
            $this->eventBus->dispatch(new PatchVideoFail(
                error: $e->getMessage(),
                videoId: $query->videoId->toRfc4122(),
            ));
            $this->logService->log('video', $video->id(), LogLevel::ERROR, 'Error Video title update', [
                'videoId' => $video->id()->toRfc4122(),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        $this->eventBus->dispatch(new PatchVideoSuccess(
            videoId: $query->videoId->toRfc4122(),
            requestedByUserId: $query->requestedByUserId->toRfc4122(),
            wasTitle: $wasTitle,
            nowTitle: $video->title()->value(),
        ));
        $this->logService->log('video', $video->id(), LogLevel::INFO, 'Video title updated', ['videoId' => $video->id()->toRfc4122()]);
        $this->videoRealtimeNotifier->notifyVideoUpdated($video, 'updated', ['title' => $video->title()->value()]);
        $tasks = $this->taskRepository->findByVideoId($query->videoId);
        foreach ($tasks as $task) {
            $this->taskRealtimeNotifier->notifyTaskUpdated($task, 'updated');
        }

        return VideoItemDTO::fromDomain($video, $this->storage, $this->taskRepository);
    }
}
