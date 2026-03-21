<?php

namespace App\Application\CommandHandler\Video;

use App\Application\Command\Video\CreateVideoPreview;
use App\Application\Event\CreateVideoPreviewFail;
use App\Application\Event\CreateVideoPreviewStart;
use App\Application\Event\CreateVideoPreviewSuccess;
use App\Domain\Video\Exception\VideoPreviewGenerationFailed;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

// TODO split
#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class CreateVideoPreviewHandler
{
    public function __construct(
        private StorageInterface $storage,
        #[Autowire(service: 'messenger.bus.event')]
        private MessageBusInterface $eventBus,
        private VideoRepositoryInterface $videoRepository,
    ) {
    }

    public function __invoke(CreateVideoPreview $command): void
    {
        $video = $command->video();
        $videoId = $video->id()?->toRfc4122();

        try {
            $this->eventBus->dispatch(new CreateVideoPreviewStart($videoId));

            $inputPath = $this->storage->getAbsolutePath($video->getSrcFilename());
            $outputPath = preg_replace('/\.[^.]+$/', '.jpg', $inputPath);

            $duration = $video->duration() ?? 0.0;
            $captureTime = min($duration, 1.0);
            $this->generatePreview($inputPath, $outputPath, $captureTime);

            $video->updateMeta(['preview' => true]);
            $this->videoRepository->save($video);

            $this->videoRepository->log($video->id(), 'info', 'Preview Created');

            $this->eventBus->dispatch(new CreateVideoPreviewSuccess($videoId));
        } catch (\Exception $e) {
            $this->videoRepository->log($video->id(), 'error', 'Error Preview creating: '. $e->getMessage());
            $this->eventBus->dispatch(new CreateVideoPreviewFail($e->getMessage(), $videoId));

            throw VideoPreviewGenerationFailed::fromVideoId($video->id()->toString(), $e->getMessage());
        }
    }

    // TODO move to App\Application\Service\Ffmpeg
    private function generatePreview(string $inputPath, string $outputPath, float $time): void
    {
        $process = new Process([
            'ffmpeg',
            '-y',
            '-ss', (string) $time,
            '-i', $inputPath,
            '-frames:v', '1',
            '-q:v', '2',
            $outputPath
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }
}
