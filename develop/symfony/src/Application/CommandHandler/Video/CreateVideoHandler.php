<?php

namespace App\Application\CommandHandler\Video;

use App\Application\Command\Video\CreateVideo;
use App\Application\Command\Video\ExtractVideoMetadata;
use App\Application\DTO\VideoItemDTO;
use App\Application\Event\CreateVideoFail;
use App\Application\Event\CreateVideoStart;
use App\Application\Event\CreateVideoSuccess;
use App\Application\Factory\FlashNotificationFactory;
use App\Application\Factory\VideoFactory;
use App\Application\Service\Video\VideoRealtimeNotifier;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use Psr\Log\LogLevel;
use App\Application\Logging\LogServiceInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class CreateVideoHandler
{
    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
        #[Autowire(service: 'messenger.bus.event')]
        private MessageBusInterface $eventBus,
        private VideoRepositoryInterface $videoRepository,
        private VideoRealtimeNotifier $notifier,
        private LogServiceInterface $logService,
        private StorageInterface $storage,
        private VideoFactory $videoFactory,
        private FlashNotificationFactory $flashNotificationFactory,
        private TaskRepositoryInterface $taskRepository,
    ) {
    }

    public function __invoke(CreateVideo $command): void
    {
        try {
            $this->eventBus->dispatch(new CreateVideoStart(
                userId: $command->userId()->toRfc4122(),
                filename: $command->file()->getName(),
            ));

            $video = $this->videoFactory->fromCreateVideo($command);
            $video = $this->videoRepository->save($video);

            $sourceKey = $this->storage->putFromPath(
                $command->file()->getFilePath(),
                $this->storage->sourceKey($video),
            );
            $video->updateMeta([
                'sourceKey' => $sourceKey,
            ]);
            $video = $this->videoRepository->save($video);

            $this->logService->log('video', $video->id(), LogLevel::INFO, 'Video created', [
                'video' => VideoItemDTO::fromDomain($video, $this->storage, $this->taskRepository),
                'file' => $command->file()->details(),
            ]);
            $this->logService->log('user', $command->userId(), LogLevel::INFO, 'User uploaded video', [
                'videoId' => $video->id()?->toRfc4122(),
                'file' => $command->file()->details(),
            ]);

            $this->notifier->notifyVideoUpdated($video, 'uploaded', [
                'notification' => $this->flashNotificationFactory->uploadCompleted($video)->toArray(),
            ]);

            $this->commandBus->dispatch(new ExtractVideoMetadata($video));
            $this->eventBus->dispatch(new CreateVideoSuccess(
                videoId: $video->id()?->toRfc4122(),
                userId: $command->userId()->toRfc4122(),
            ));
        } catch (\Exception $e) {
            $this->eventBus->dispatch(new CreateVideoFail(
                error: $e->getMessage(),
                userId: $command->userId()->toRfc4122(),
                filename: $command->file()->getName(),
            ));
        }
    }
}
