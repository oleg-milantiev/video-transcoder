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
use App\Application\Logging\LogServiceInterface;
use App\Application\Service\Mercure\FlashRealtimeNotifier;
use App\Application\Service\Storage\StorageRealtimeNotifier;
use App\Application\Service\Video\UrlVideoDownloader;
use App\Application\Service\Video\VideoRealtimeNotifier;
use App\Domain\User\Entity\Tariff;
use App\Domain\User\Entity\User;
use App\Domain\User\Exception\TariffNotFound;
use App\Domain\User\Exception\UserNotFound;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\Video\Exception\VideoFileNotFound;
use App\Domain\Video\Exception\VideoSizeExceedsQuota;
use App\Domain\Video\Repository\StorageRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use Psr\Log\LogLevel;
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
        private StorageRepositoryInterface $storageRepository,
        private UserRepositoryInterface $userRepository,
        private VideoRealtimeNotifier $videoRealtimeNotifier,
        private FlashRealtimeNotifier $flashRealtimeNotifier,
        private LogServiceInterface $logService,
        private StorageInterface $storage,
        private VideoFactory $videoFactory,
        private FlashNotificationFactory $flashNotificationFactory,
        private TaskRepositoryInterface $taskRepository,
        private UrlVideoDownloader $urlDownloader,
        private StorageRealtimeNotifier $storageNotifier,
    ) {
    }

    // todo сократить (вынести в private, как resolveUser|Tariff
    public function __invoke(CreateVideo $command): void
    {
        try {
            $this->eventBus->dispatch(
                new CreateVideoStart(
                    userId: $command->userId()->toRfc4122(),
                    filename: $command->filename(),
                )
            );

            // ── resolve file path + size ──────────────────────────────────────
            $meta = [];

            $user = $this->resolveUser($command);
            $tariff = $this->resolveTariff($command, $user);
            $maxSizeMb = $tariff->videoSize()->value();

            if ($command->url() !== null) {
                $startTime = microtime(true);
                $downloaded = $this->urlDownloader->download(
                    $command->url(),
                    (int)round($maxSizeMb * 1024 * 1024),
                );
                $durationSec = microtime(true) - $startTime;

                $filePath = $downloaded['path'];
                $fileSize = $downloaded['size'];
                $videoFilename = $downloaded['filename'];

                $meta = [
                    'downloadUrl' => $command->url(),
                    'downloadDurationSec' => round($durationSec, 2),
                    'downloadSpeed' => $durationSec > 0.0
                        ? round($fileSize / $durationSec, 3)
                        : null,
                ];
            } else {
                // ── TusFile path: check file exists before touching the DB ────────
                $file = $command->file();
                if ($file === null) {
                    throw VideoFileNotFound::cannotDetermineSize('unknown');
                }

                $filePath = $file->getFilePath();

                if (!file_exists($filePath)) {
                    $this->logService->log('video', 'create', null, LogLevel::CRITICAL, 'File not exists', [
                        'file' => $file->details(),
                    ]);
                    throw VideoFileNotFound::cannotDetermineSize($filePath);
                }

                $fileSize = @filesize($filePath);
                if ($fileSize === false) {
                    $this->logService->log('video', 'create', null, LogLevel::CRITICAL, 'File size not found', [
                        'file' => $file->details(),
                    ]);
                    throw VideoFileNotFound::cannotDetermineSize($filePath);
                }

                $videoFilename = $file->details()['metadata']['originalName'] ?? $file->getName();
            }
            $meta['size'] = $fileSize;
            if ($command->session !== null) {
                $meta['session'] = $command->session;
            }

            // ── file-size vs tariff limit ─────────────────────────────────────
            $fileSizeMb = $fileSize / (1024 * 1024);
            if ($fileSizeMb > $maxSizeMb) {
                @unlink($filePath);
                $this->flashRealtimeNotifier->notify(
                    $command->userId(),
                    $this->flashNotificationFactory->uploadFailed(null, 'File size exceeds '.$maxSizeMb.' MB')
                );
                throw VideoSizeExceedsQuota::fromSize($fileSizeMb, $maxSizeMb);
            }

            // ── storage-quota check ───────────────────────────────────────────
            $storageNowMb = $this->storageRepository->getUsedStorageSize($user->id()) / 1024 / 1024;
            $storageCapacityMb = $tariff->storageGb()->value() * 1024;
            if ($fileSizeMb + $storageNowMb > $storageCapacityMb) {
                @unlink($filePath);
                $this->flashRealtimeNotifier->notify(
                    $command->userId(),
                    $this->flashNotificationFactory->uploadFailed(null, 'The video doesn\'t fit in the storage')
                );
                throw StorageSizeExceedsQuota::create($fileSizeMb, $storageNowMb, $storageCapacityMb);
            }

            // ── persist video entity ──────────────────────────────────────────
            $video = $this->videoFactory->fromFilename($videoFilename, $command->userId());
            $video = $this->videoRepository->save($video);

            $sourceKey = $this->storage->putFromPath(
                $filePath,
                $this->storage->sourceKey($video),
            );

            $meta['sourceKey'] = $sourceKey;
            $video->updateMeta($meta);
            $video = $this->videoRepository->save($video);

            $this->logService->log('video', 'create', $video->id(), LogLevel::INFO, 'Video created', [
                'user' => $user,
                'video' => VideoItemDTO::fromDomain($video, $this->storage, $this->taskRepository, $tariff),
                'file' => $command->file()?->details() ?? ['url' => $command->url()],
            ]);
            $this->logService->log('user', 'upload', $command->userId(), LogLevel::INFO, 'User uploaded video', [
                'videoId' => $video->id()?->toRfc4122(),
                'file' => $command->file()?->details() ?? ['url' => $command->url()],
            ]);

            $this->videoRealtimeNotifier->notifyVideoUpdated($video, 'uploaded', [
                'notification' => $this->flashNotificationFactory->uploadCompleted($video)->toArray(),
            ]);
            $this->storageNotifier->notifyStorageUpdated($video->userId());

            $this->eventBus->dispatch(
                new CreateVideoSuccess(
                    videoId: $video->id()?->toRfc4122(),
                    userId: $command->userId()->toRfc4122(),
                )
            );
            $this->commandBus->dispatch(new ExtractVideoMetadata($video));
        } catch (\Throwable $e) {
            // todo log?
            $this->eventBus->dispatch(
                new CreateVideoFail(
                    error: $e->getMessage(),
                    userId: $command->userId()->toRfc4122(),
                    filename: $command->filename(),
                )
            );
        }
    }

    private function resolveUser(CreateVideo $command): User
    {
        $user = $this->userRepository->findById($command->userId());
        if ($user === null) {
            $this->logService->log('video', 'create', null, LogLevel::CRITICAL, 'User not found', [
                'file' => $command->file()?->details() ?? ['url' => $command->url()],
            ]);
            throw UserNotFound::byId($command->userId()->toRfc4122());
        }

        return $user;
    }

    private function resolveTariff(CreateVideo $command, User $user): Tariff
    {
        $tariff = $user->tariff();
        if ($tariff === null) {
            $this->logService->log('video', 'create', null, LogLevel::ERROR, 'User without tariff', [
                'userId' => $command->userId()->toRfc4122(),
                'file' => $command->file()?->details() ?? ['url' => $command->url()],
            ]);
            throw TariffNotFound::forUser($command->userId()->toRfc4122());
        }

        return $tariff;
    }
}
