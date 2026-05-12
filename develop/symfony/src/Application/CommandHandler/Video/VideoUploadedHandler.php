<?php
declare(strict_types=1);

namespace App\Application\CommandHandler\Video;

use App\Application\Command\Video\ExtractVideoMetadata;
use App\Application\Command\Video\VideoUploaded;
use App\Application\Event\VideoUploadedFail;
use App\Application\Event\VideoUploadedStart;
use App\Application\Event\VideoUploadedSuccess;
use App\Application\Exception\StorageSizeExceedsQuota;
use App\Application\Factory\FlashNotificationFactory;
use App\Application\Logging\LogServiceInterface;
use App\Application\Service\Storage\StorageRealtimeNotifier;
use App\Application\Service\Video\VideoRealtimeNotifier;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Entity\Tariff;
use App\Domain\User\Entity\User;
use App\Domain\User\Exception\TariffNotFound;
use App\Domain\User\Exception\UserNotFound;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\Video\Exception\VideoFileNotFound;
use App\Domain\Video\Exception\VideoSizeExceedsQuota;
use App\Domain\Video\Repository\StorageRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class VideoUploadedHandler
{
    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
        #[Autowire(service: 'messenger.bus.event')]
        private MessageBusInterface $eventBus,
        private VideoRepositoryInterface $videoRepository,
        private StorageRepositoryInterface $storageRepository,
        private UserRepositoryInterface $userRepository,
        private VideoRealtimeNotifier $videoRealtimeNotifier,
        private LogServiceInterface $logService,
        private StorageInterface $storage,
        private FlashNotificationFactory $flashNotificationFactory,
        private StorageRealtimeNotifier $storageNotifier,
    ) {
    }

    public function __invoke(VideoUploaded $command): void
    {
        $video = $command->video();

        try {
            $this->eventBus->dispatch(
                new VideoUploadedStart(
                    videoId: $video->id()?->toRfc4122() ?? '',
                    userId: $video->userId()->toRfc4122(),
                )
            );

            $user = $this->resolveUser($video->userId());
            $tariff = $this->resolveTariff($user, $video->userId());
            $maxSizeMb = $tariff->videoSize()->value();

            $filePath = $command->filePath();

            if (!file_exists($filePath)) {
                $this->logService->log('video', 'upload', $video->id(), LogLevel::CRITICAL, 'File not exists', [
                    'filePath' => $filePath,
                ]);
                throw VideoFileNotFound::cannotDetermineSize($filePath);
            }

            $fileSize = @filesize($filePath);
            if ($fileSize === false) {
                $this->logService->log('video', 'upload', $video->id(), LogLevel::CRITICAL, 'File size not found', [
                    'filePath' => $filePath,
                ]);
                throw VideoFileNotFound::cannotDetermineSize($filePath);
            }

            // ── file-size vs tariff limit ─────────────────────────────────────
            $fileSizeMb = $fileSize / (1024 * 1024);
            if ($fileSizeMb > $maxSizeMb) {
                @unlink($filePath);
                throw VideoSizeExceedsQuota::fromSize($fileSizeMb, $maxSizeMb);
            }

            // ── storage-quota check ───────────────────────────────────────────
            $storageNowMb = $this->storageRepository->getUsedStorageSize($user->id()) / 1024 / 1024;
            $storageCapacityMb = $tariff->storageGb()->value() * 1024;
            if ($storageNowMb > $storageCapacityMb) {
                @unlink($filePath);
                throw StorageSizeExceedsQuota::create($fileSizeMb, $storageNowMb, $storageCapacityMb);
            }

            // ── upload to storage & persist ───────────────────────────────────
            $sourceKey = $this->storage->putFromPath(
                $filePath,
                $this->storage->sourceKey($video),
            );

            $video->updateMeta([
                'size' => $fileSize,
                'sourceKey' => $sourceKey,
            ]);
            $video->markLoaded();
            $video = $this->videoRepository->save($video);

            $this->logService->log('video', 'upload', $video->id(), LogLevel::INFO, 'Video uploaded', [
                'userId' => $user->id(),
                'videoId' => $video->id(),
                'filename' => $command->filename(),
            ]);
            $this->logService->log('user', 'upload', $video->userId(), LogLevel::INFO, 'User uploaded video', [
                'videoId' => $video->id()?->toRfc4122(),
                'filename' => $command->filename(),
            ]);

            $this->videoRealtimeNotifier->notifyVideoUpdated($video, 'uploaded', [
                'notification' => $this->flashNotificationFactory->uploadCompleted($video)->toArray(),
            ]);
            $this->storageNotifier->notifyStorageUpdated($video->userId());

            $this->eventBus->dispatch(
                new VideoUploadedSuccess(
                    videoId: $video->id()?->toRfc4122() ?? '',
                    userId: $video->userId()->toRfc4122(),
                )
            );
            $this->commandBus->dispatch(new ExtractVideoMetadata($video));
        } catch (\Throwable $e) {
            $this->logService->log('video', 'upload', $video->id(), LogLevel::ERROR, 'Video upload error', [
                'filename' => $command->filename(),
            ]);

            $video->updateMeta([
                'deleteReason' => $e->getMessage(),
            ]);
            $video->markDeleted([]);
            $this->videoRepository->save($video);

            $this->videoRealtimeNotifier->notifyVideoUpdated($video, 'uploaded', [
                'notification' => $this->flashNotificationFactory->uploadFailed($video, $e->getMessage())->toArray(),
            ]);

            $this->eventBus->dispatch(
                new VideoUploadedFail(
                    error: $e->getMessage(),
                    videoId: $video->id()?->toRfc4122() ?? '',
                    userId: $video->userId()->toRfc4122(),
                )
            );
        }
    }

    private function resolveUser(Uuid $userId): User
    {
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            $this->logService->log('video', 'upload', null, LogLevel::CRITICAL, 'User not found', [
                'userId' => $userId->toRfc4122(),
            ]);
            throw UserNotFound::byId($userId->toRfc4122());
        }

        return $user;
    }

    private function resolveTariff(User $user, Uuid $userId): Tariff
    {
        $tariff = $user->tariff();
        if ($tariff === null) {
            $this->logService->log('video', 'upload', null, LogLevel::ERROR, 'User without tariff', [
                'userId' => $userId->toRfc4122(),
            ]);
            throw TariffNotFound::forUser($userId->toRfc4122());
        }

        return $tariff;
    }
}
