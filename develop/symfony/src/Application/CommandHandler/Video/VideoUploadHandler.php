<?php
declare(strict_types=1);

namespace App\Application\CommandHandler\Video;

use App\Application\Command\Video\VideoUpload;
use App\Application\Command\Video\VideoUploaded;
use App\Application\Event\VideoUploadedFail;
use App\Application\Factory\FlashNotificationFactory;
use App\Application\Logging\LogServiceInterface;
use App\Application\Service\Mercure\FlashRealtimeNotifier;
use App\Application\Service\Video\UrlVideoDownloader;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\ValueObject\VideoTitle;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handles async URL-based video ingestion:
 * 1. Receives the pre-created Video entity (loading=true) from the command.
 * 2. Downloads the remote file while enforcing tariff size limit.
 * 3. Marks the video as loaded (loading=false) and saves it.
 * 4. Dispatches VideoUploaded to perform quota checks, S3 upload and metadata extraction.
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
            // todo надо тут уже знать size. А лучше даже выше, в VideoApiController::upload
            // todo надо блокировать Storage на этот size через updateMeta[size] и проверку на свободное место

            // Download the remote file
            $startTime = microtime(true);
            $downloaded = $this->urlDownloader->download($command->url());
            $durationSec = microtime(true) - $startTime;

            $actualFilename = $downloaded['filename'];
            $urlFilename = basename((string)(parse_url($command->url(), PHP_URL_PATH) ?? '')) ?: 'video.mp4';

            // Update title if the download revealed a better filename
            if ($actualFilename !== $urlFilename) {
                $video->changeTitle(new VideoTitle(pathinfo($actualFilename, PATHINFO_FILENAME)));
            }

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
