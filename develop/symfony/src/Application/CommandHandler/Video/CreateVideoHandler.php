<?php
declare(strict_types=1);

namespace App\Application\CommandHandler\Video;

use App\Application\Command\Video\CreateVideo;
use App\Application\Command\Video\ExtractVideoMetadata;
use App\Application\DTO\VideoItemDTO;
use App\Application\Event\CreateVideoFail;
use App\Application\Event\CreateVideoStart;
use App\Application\Event\CreateVideoSuccess;
use App\Application\Exception\StorageSizeExceedsQuota;
use App\Application\Factory\FlashNotificationFactory;
use App\Application\Factory\VideoFactory;
use App\Application\Service\Video\VideoRealtimeNotifier;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Exception\VideoSizeExceedsQuota;
use App\Domain\Video\Exception\VideoFileNotFound;
use App\Domain\User\Exception\UserNotFound;
use App\Domain\User\Exception\TariffNotFound;
use App\Domain\User\Repository\UserRepositoryInterface;
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
        private UserRepositoryInterface $userRepository,
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

            $filePath = $command->file()->getFilePath();

            if (!file_exists($filePath)) {
                throw VideoFileNotFound::cannotDetermineSize($filePath);
            }

            // file size tariff limits
            $fileSize = @filesize($filePath);
            if ($fileSize === false) {
                throw VideoFileNotFound::cannotDetermineSize($filePath);
            }

            $fileSizeMb = $fileSize / (1024 * 1024);

            $user = $this->userRepository->findById($command->userId());
            if ($user === null) {
                throw UserNotFound::byId($command->userId()->toRfc4122());
            }

            $tariff = $user->tariff();
            if ($tariff === null) {
                throw TariffNotFound::forUser($command->userId()->toRfc4122());
            }

            $maxSizeMb = $tariff->videoSize()->value();
            if ($fileSizeMb > $maxSizeMb) {
                unlink($filePath);
                // todo use app exception
                throw VideoSizeExceedsQuota::fromSize($fileSizeMb, $maxSizeMb);
            }

            // storage size tariff limits
            $storageNowMb = ($this->videoRepository->getStorageSize($user->id()) + $this->taskRepository->getStorageSize($user->id()))/1024/1024;
            $storageCapacityMb = $tariff->storageGb()->value()*1024;
            if ($fileSizeMb + $storageNowMb > $storageCapacityMb) {
                unlink($filePath);
                throw StorageSizeExceedsQuota::create($fileSizeMb, $storageNowMb, $storageCapacityMb);
            }

            // its ok, lets store video!
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

            $this->eventBus->dispatch(new CreateVideoSuccess(
                videoId: $video->id()?->toRfc4122(),
                userId: $command->userId()->toRfc4122(),
            ));
            $this->commandBus->dispatch(new ExtractVideoMetadata($video));
        } catch (\Exception $e) {
            $this->eventBus->dispatch(new CreateVideoFail(
                error: $e->getMessage(),
                userId: $command->userId()->toRfc4122(),
                filename: $command->file()->getName(),
            ));
        }
    }
}
