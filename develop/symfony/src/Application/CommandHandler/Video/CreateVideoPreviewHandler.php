<?php

namespace App\Application\CommandHandler\Video;

use App\Application\Command\Video\CreateVideoPreview;
use App\Domain\Video\Event\VideoPreviewGenerationFinished;
use App\Domain\Video\Event\VideoPreviewGenerationStarted;
use App\Domain\Video\Exception\VideoPreviewGenerationFailed;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

#[AsMessageHandler]
final readonly class CreateVideoPreviewHandler
{
    public function __construct(
        private VideoRepositoryInterface $videoRepository,
        private StorageInterface         $storage,
        private MessageBusInterface      $messageBus
    ) {
    }

    public function __invoke(CreateVideoPreview $command): void
    {
        $videoId = $command->getVideoId();
        $video = $this->videoRepository->findById($videoId);

        if (!$video) {
            return;
        }

        $this->messageBus->dispatch(new VideoPreviewGenerationStarted($videoId));

        try {
            $inputPath = $this->storage->getAbsolutePath($video->getSrcFilename());
            $outputPath = dirname($inputPath) . DIRECTORY_SEPARATOR . 'preview.jpg';

            $duration = $video->duration() ?? 0.0;

            // Генерация превью
            $captureTime = min($duration, 1.0);
            $this->generatePreview($inputPath, $outputPath, $captureTime);

            $this->messageBus->dispatch(new VideoPreviewGenerationFinished($videoId));
        } catch (\Exception $e) {
            throw VideoPreviewGenerationFailed::fromVideoId($videoId->toString(), $e->getMessage());
        }
    }

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
