<?php

namespace App\Application\CommandHandler\Video;

use App\Application\Command\Video\CreateVideoPreview;
use App\Application\Command\Video\ExtractVideoMetadata;
use App\Application\Event\ExtractVideoMetadataFail;
use App\Application\Event\ExtractVideoMetadataStart;
use App\Application\Event\ExtractVideoMetadataSuccess;
use App\Application\Service\Video\VideoRealtimeNotifier;
use Psr\Log\LogLevel;
use App\Application\Logging\LogServiceInterface;
use App\Domain\Video\Exception\VideoMetadataExtractionFailed;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
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
        $video = $command->video();
        $videoId = $video->id()?->toRfc4122();

        try {
            $this->eventBus->dispatch(new ExtractVideoMetadataStart($videoId));

            $inputPath = $this->storage->localPathForRead($this->storage->sourceKey($video));
            $metadata = $this->videoMetadataExtractor->extract($inputPath);
            $this->logger->debug('Extract Video Metadata: data extracted', [
                'meta' => $metadata,
            ]);

            $video->updateMeta($metadata);
            $this->videoRepository->save($video);

            $this->logService->log('video', $video->id(), LogLevel::INFO, 'Metadata extracted');
            $this->notifier->notifyVideoUpdated($video, 'meta');

            $this->commandBus->dispatch(new CreateVideoPreview($video));
            $this->eventBus->dispatch(new ExtractVideoMetadataSuccess($videoId));
        } catch (\Exception $e) {
            $this->logService->log('video', $video->id(), LogLevel::ERROR, 'Metadata extraction error', [
                'message' => $e->getMessage(),
            ]);
            $this->eventBus->dispatch(new ExtractVideoMetadataFail($e->getMessage(), $videoId));

            throw VideoMetadataExtractionFailed::fromVideoId($video->id()->toString(), $e->getMessage());
        }
    }

}
