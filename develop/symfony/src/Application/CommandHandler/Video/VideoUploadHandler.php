<?php
declare(strict_types=1);

namespace App\Application\CommandHandler\Video;

use App\Application\Command\Video\VideoUpload;
use App\Application\Command\Video\VideoUploaded;
use App\Application\Event\VideoUploadedFail;
use App\Application\Exception\StorageSizeExceedsQuota;
use App\Application\Factory\FlashNotificationFactory;
use App\Application\Logging\LogServiceInterface;
use App\Application\Service\Mercure\FlashRealtimeNotifier;
use App\Application\Service\Video\UrlVideoDownloader;
use App\Domain\User\Exception\TariffNotFound;
use App\Domain\User\Exception\UserNotFound;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\Video\Repository\StorageRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\ValueObject\VideoTitle;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handles async URL-based video ingestion:
 * 1. Receives the pre-created Video entity (loading=true) from the command.
 * 2. Probes the remote URL via HEAD to obtain content-length; saves it to video.meta['size'].
 * 3. Checks storage quota (the new file's size is already counted via getUsedStorageSize).
 * 4. Downloads the remote file.
 * 5. Marks the video as loaded (loading=false) and saves it.
 * 6. Dispatches VideoUploaded (sync) to perform S3 upload and metadata extraction.
 */
#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class VideoUploadHandler
{
    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
        #[Autowire(service: 'messenger.bus.event')]
        private MessageBusInterface $eventBus,
        private VideoRepositoryInterface $videoRepository,
        private UserRepositoryInterface $userRepository,
        private StorageRepositoryInterface $storageRepository,
        private UrlVideoDownloader $urlDownloader,
        private FlashRealtimeNotifier $flashRealtimeNotifier,
        private FlashNotificationFactory $flashNotificationFactory,
        private LogServiceInterface $logService,
    ) {
    }

    public function __invoke(VideoUpload $command): void
    {
        $video = $command->video();

        try {
            $user = $this->userRepository->findById($video->userId());
            if ($user === null) {
                throw UserNotFound::byId($video->userId()->toRfc4122());
            }
            $tariff = $user->tariff();
            if ($tariff === null) {
                throw TariffNotFound::forUser($video->userId()->toRfc4122());
            }

            $headers = $this->urlDownloader->headRequest($command->url());
            if (!isset($headers['content-length'])) {
                throw new \RuntimeException('Content-Length header not found');
            }

            $contentLength = (int)$headers['content-length'];
            $video->updateMeta([
                'size' => $contentLength,
            ]);
            $video = $this->videoRepository->save($video);

            $storageCapacityBytes = (int)($tariff->storageGb()->value() * 1024 * 1024 * 1024);
            $usedBytes = $this->storageRepository->getUsedStorageSize($video->userId());
            if ($usedBytes > $storageCapacityBytes) {
                $video->updateMeta([
                    'deleteReason' => 'Not enough storage space.',
                ]);
                $video->markDeleted([]);
                $this->videoRepository->save($video);
                throw new StorageSizeExceedsQuota('Storage quota exceeded');
            }

            // ── download the remote file ──────────────────────────────────────
            $startTime = microtime(true);
            $downloaded = $this->urlDownloader->download($command->url(), $headers, $contentLength);
            $durationSec = microtime(true) - $startTime;

            $actualFilename = $downloaded['filename'];
            $urlFilename = basename((string)(parse_url($command->url(), PHP_URL_PATH) ?? '')) ?: 'video.mp4';

            // Update title if the download revealed a better filename
            if ($actualFilename !== $urlFilename) {
                $video->changeTitle(new VideoTitle(pathinfo($actualFilename, PATHINFO_FILENAME)));
            }

            // Mark as loaded and persist
            $video->markLoaded();
            $video->updateMeta([
                'downloadUrl' => $command->url(),
                'downloadDurationSec' => round($durationSec, 2),
                'downloadSpeed' => $durationSec > 0.0
                    ? round($downloaded['size'] / $durationSec, 3)
                    : null,
            ]);
            $this->videoRepository->save($video);

            $this->commandBus->dispatch(new VideoUploaded(
                video: $video,
                filePath: $downloaded['path'],
                filename: $actualFilename,
            ));
        } catch (\Throwable $e) {
            $this->logService->log('video', 'upload', $video->id(), LogLevel::ERROR, 'URL upload failed', [
                'url' => $command->url(),
                'message' => $e->getMessage(),
            ]);

            $this->flashRealtimeNotifier->notify(
                $video->userId(),
                $this->flashNotificationFactory->uploadFailed($video, $e->getMessage())
            );

            // todo убрать эту расширяемость к чёрту
            $this->eventBus->dispatch(new VideoUploadedFail(
                error: $e->getMessage(),
                videoId: $video->id()?->toRfc4122() ?? '',
                userId: $video->userId()->toRfc4122(),
            ));
        }
    }
}
