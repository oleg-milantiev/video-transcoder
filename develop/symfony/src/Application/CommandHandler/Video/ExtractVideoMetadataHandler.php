<?php

namespace App\Application\CommandHandler\Video;

use App\Application\Command\Video\CreateVideoPreview;
use App\Application\Command\Video\ExtractVideoMetadata;
use App\Domain\Video\Exception\VideoMetadataExtractionFailed;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

#[AsMessageHandler]
final readonly class ExtractVideoMetadataHandler
{
    public function __construct(
        private VideoRepositoryInterface $videoRepository,
        private StorageInterface $storage,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function __invoke(ExtractVideoMetadata $command): void
    {
        $video = $command->video();

        // TODO split command and event message busses
//        $this->messageBus->dispatch(new VideoMetadataExtractionStarted($video));

        try {
            $this->logger->debug('Extract Video Metadata: started');

            $inputPath = $this->storage->getAbsolutePath($video->getSrcFilename());
            $metadata = $this->getVideoMetadata($inputPath);
            $this->logger->debug('Extract Video Metadata: data extracted', [
                'meta' => $metadata,
            ]);

            $video->updateMeta($metadata);
            $this->videoRepository->save($video);
            $this->logger->debug('Extract Video Metadata: entity updated');

            $this->videoRepository->log($video->id(), 'info', 'Metadata extracted');

            // TODO split command and event message busses
//            $this->messageBus->dispatch(new VideoMetadataExtractionFinished($video));
            $this->messageBus->dispatch(new CreateVideoPreview($video));
        } catch (\Exception $e) {
            $this->videoRepository->log($video->id(), 'error', 'Metadata extraction error: '. $e->getMessage());

            throw VideoMetadataExtractionFailed::fromVideoId($video->id()->toString(), $e->getMessage());
        }
    }

    private function getVideoMetadata(string $path): array
    {
        $process = new Process([
            'ffprobe',
            '-v', 'quiet',
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
            $path
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        // ex.: ffprobe.json
        $output = json_decode($process->getOutput(), true);

        if ($output === null) {
            throw new \RuntimeException('Failed to parse ffprobe output.');
        }

        $videoStream = null;
        foreach ($output['streams'] ?? [] as $stream) {
            if (($stream['codec_type'] ?? '') === 'video') {
                $videoStream = $stream;
                break;
            }
        }

        $metadata = [
            'duration' => (float) ($output['format']['duration'] ?? 0.0),
            'bitrate' => (int) ($output['format']['bit_rate'] ?? 0),
            'format' => $output['format']['format_name'] ?? 'unknown',
            'size' => (int) ($output['format']['size'] ?? 0),
        ];

        if ($videoStream) {
            $metadata['width'] = (int) ($videoStream['width'] ?? 0);
            $metadata['height'] = (int) ($videoStream['height'] ?? 0);
            $metadata['codec'] = $videoStream['codec_name'] ?? 'unknown';
            $metadata['frame_rate'] = $videoStream['avg_frame_rate'] ?? 'unknown';
        }

        return $metadata;
    }
}
