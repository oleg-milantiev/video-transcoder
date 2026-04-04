<?php
declare(strict_types=1);

namespace App\Application\CommandHandler\Video;

use App\Application\Command\Video\CleanupDeletedVideoMedia;
use App\Application\Command\Video\CreateVideoPreview;
use App\Application\Command\Video\ExtractVideoMetadata;
use App\Application\Event\ExtractVideoMetadataFail;
use App\Application\Event\ExtractVideoMetadataStart;
use App\Application\Event\ExtractVideoMetadataSuccess;
use App\Application\Service\Video\VideoRealtimeNotifier;
use Psr\Log\LogLevel;
use App\Application\Logging\LogServiceInterface;
use App\Domain\Video\Exception\VideoMetadataExtractionFailed;
use App\Domain\Video\Exception\VideoMetadataInvalid;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Exception\UserNotFound;
use App\Domain\User\Exception\TariffNotFound;
use App\Infrastructure\Ffmpeg\VideoMetadataExtractor;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class ExtractVideoMetadataHandler
{
    public function __construct(
        private VideoRepositoryInterface $videoRepository,
        private UserRepositoryInterface $userRepository,
        private TaskRepositoryInterface $taskRepository,
        private StorageInterface $storage,
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
        #[Autowire(service: 'messenger.bus.event')]
        private MessageBusInterface $eventBus,
        private VideoMetadataExtractor $videoMetadataExtractor,
        private VideoRealtimeNotifier $notifier,
        private LogServiceInterface $logService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function __invoke(ExtractVideoMetadata $command): void
    {
        $ms = microtime(true);
        $video = $command->video();
        $videoId = $video->id()?->toRfc4122();

        try {
            $this->eventBus->dispatch(new ExtractVideoMetadataStart($videoId));

            $user = $this->userRepository->findById($video->userId());
            if ($user === null) {
                $this->handleMetadataExtractionError($video, UserNotFound::byId($video->userId()->toRfc4122()));
            }
            $tariff = $user->tariff();
            if ($tariff === null) {
                $this->handleMetadataExtractionError($video, TariffNotFound::forUser($user->id()->toRfc4122()));
            }

            $inputPath = $this->storage->localPathForRead($this->storage->sourceKey($video));
            $metadata = $this->videoMetadataExtractor->extract($inputPath);
            $this->logService->log('video', 'meta', $video->id(), LogLevel::DEBUG, 'Metadata extracted', [
                'time' => microtime(true) - $ms,
                'meta' => $metadata,
            ]);
            $video->updateMeta($metadata);
            $this->videoRepository->save($video);

            $width = $metadata['width'] ?? null;
            $height = $metadata['height'] ?? null;
            if ($width === null || $height === null) {
                $this->handleMetadataExtractionError($video, VideoMetadataInvalid::missingResolution());
            }
            $maxWidth = $tariff->maxWidth()->value();
            $maxHeight = $tariff->maxHeight()->value();
            if ($width > $maxWidth || $height > $maxHeight) {
                $this->handleMetadataExtractionError($video, VideoMetadataInvalid::resolutionExceedsLimit($width, $height, $maxWidth, $maxHeight));
            }

            $duration = $video->duration();
            if ($duration === null) {
                $this->handleMetadataExtractionError($video, VideoMetadataInvalid::missingDuration());
            }
            $maxDuration = $tariff->videoDuration()->value();
            if ($duration > $maxDuration) {
                $this->handleMetadataExtractionError($video, VideoMetadataInvalid::durationExceedsLimit($duration, $maxDuration));
            }

            $this->notifier->notifyVideoUpdated($video, 'meta');
            $this->logService->log('video', 'meta', $video->id(), LogLevel::INFO, 'Metadata extracted and saved', [
                'time' => microtime(true) - $ms,
            ]);

            $this->commandBus->dispatch(new CreateVideoPreview($video));
            $this->eventBus->dispatch(new ExtractVideoMetadataSuccess($videoId));
        } catch (\Exception $e) {
            $this->handleMetadataExtractionError($video, $e);
        }
    }

    /**
     * @throws ExceptionInterface
     */
    private function handleMetadataExtractionError($video, \Exception $e): void
    {
        $videoId = $video->id()?->toRfc4122();

        try {
            $tasks = $this->taskRepository->findByVideoId($video->id());
            $video->markDeleted($tasks);
            $this->videoRepository->save($video);

            $this->commandBus->dispatch(new CleanupDeletedVideoMedia($video->id()));
        } catch (\Exception $deleteError) {
            $this->logger->error('Failed to mark video for deletion', [
                'videoId' => $videoId,
                'error' => $deleteError->getMessage(),
            ]);
        }

        $this->logService->log('video', 'meta', $video->id(), LogLevel::ERROR, 'Metadata validation failed', [
            'message' => $e->getMessage(),
        ]);

        $this->eventBus->dispatch(new ExtractVideoMetadataFail($e->getMessage(), $videoId));

        throw VideoMetadataExtractionFailed::fromVideoId($video->id()->toString(), $e->getMessage());
    }
}
