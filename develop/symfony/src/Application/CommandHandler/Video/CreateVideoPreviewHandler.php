<?php

namespace App\Application\CommandHandler\Video;

use App\Application\Command\Video\CreateVideoPreview;
use App\Domain\Video\Exception\VideoPreviewGenerationFailed;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
//use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

#[AsMessageHandler]
final readonly class CreateVideoPreviewHandler
{
    public function __construct(
        private StorageInterface $storage,
//        private MessageBusInterface $messageBus,
        private VideoRepositoryInterface $videoRepository,
    ) {
    }

    public function __invoke(CreateVideoPreview $command): void
    {
        $video = $command->video();

        // TODO split command and event message busses
//        $this->messageBus->dispatch(new VideoPreviewGenerationStarted($video));

        try {
            $inputPath = $this->storage->getAbsolutePath($video->getSrcFilename());
            $outputPath = preg_replace('/\.[^.]+$/', '.jpg', $inputPath);

            $duration = $video->duration() ?? 0.0;
            $captureTime = min($duration, 1.0);
            $this->generatePreview($inputPath, $outputPath, $captureTime);

            $video->updateMeta(['preview' => true]);
            $this->videoRepository->save($video);

            $this->videoRepository->log($video->id(), 'info', 'Preview Created');

            // TODO split command and event message busses
//            $this->messageBus->dispatch(new VideoPreviewGenerationFinished($video));
        } catch (\Exception $e) {
            $this->videoRepository->log($video->id(), 'error', 'Error Preview creating: '. $e->getMessage());

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
