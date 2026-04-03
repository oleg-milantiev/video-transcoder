<?php
declare(strict_types=1);

namespace App\Application\CommandHandler\Video;

use App\Application\Command\Video\CreateVideoPreview;
use App\Application\Event\CreateVideoPreviewFail;
use App\Application\Event\CreateVideoPreviewStart;
use App\Application\Event\CreateVideoPreviewSuccess;
use App\Application\Service\Video\VideoRealtimeNotifier;
use Psr\Log\LogLevel;
use App\Application\Logging\LogServiceInterface;
use App\Domain\Video\Exception\VideoPreviewGenerationFailed;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Infrastructure\Ffmpeg\VideoPreviewGenerator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class CreateVideoPreviewHandler
{
    public function __construct(
        private StorageInterface $storage,
        #[Autowire(service: 'messenger.bus.event')]
        private MessageBusInterface $eventBus,
        private LogServiceInterface $logService,
        private VideoRepositoryInterface $videoRepository,
        private VideoRealtimeNotifier $notifier,
        private VideoPreviewGenerator $videoPreviewGenerator,
    ) {
    }

    public function __invoke(CreateVideoPreview $command): void
    {
        $video = $command->video();
        $videoId = $video->id()?->toRfc4122();

        try {
            $this->eventBus->dispatch(new CreateVideoPreviewStart($videoId));

            $sourceKey = $this->storage->sourceKey($video);
            $previewKey = $this->storage->previewKey($video);
            $inputPath = $this->storage->localPathForRead($sourceKey);
            $outputPath = $this->storage->localPathForWrite($previewKey);

            $duration = $video->duration() ?? 0.0;
            $captureTime = min($duration, 1.0);
            $this->videoPreviewGenerator->generate($inputPath, $outputPath, $captureTime);
            $this->storage->publishLocalFile($outputPath, $previewKey);

            $video->updateMeta(['preview' => true]);
            $this->videoRepository->save($video);

            $this->logService->log('video', 'preview', $video->id(), LogLevel::INFO, 'Preview Created');
            $this->notifier->notifyVideoUpdated($video, 'preview');

            $this->eventBus->dispatch(new CreateVideoPreviewSuccess($videoId));
        } catch (\Exception $e) {
            $this->logService->log('video', 'preview', $video->id(), LogLevel::ERROR, 'Error Create Preview', [
                'message' => $e->getMessage(),
            ]);
            $this->eventBus->dispatch(new CreateVideoPreviewFail($e->getMessage(), $videoId));

            throw VideoPreviewGenerationFailed::fromVideoId($video->id()->toString(), $e->getMessage());
        }
    }

}
