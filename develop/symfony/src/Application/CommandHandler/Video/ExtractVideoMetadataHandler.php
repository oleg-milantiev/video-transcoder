<?php

namespace App\Application\CommandHandler\Video;

use App\Application\Command\Video\ExtractVideoMetadata;
use App\Domain\Video\Event\VideoMetadataExtractionFinished;
use App\Domain\Video\Event\VideoMetadataExtractionStarted;
use App\Domain\Video\Exception\VideoMetadataExtractionFailed;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
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
        private StorageInterface         $storage,
        private MessageBusInterface      $messageBus
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function __invoke(ExtractVideoMetadata $command): void
    {
        $video = $command->video();

        $this->messageBus->dispatch(new VideoMetadataExtractionStarted($video));

        try {
            $inputPath = $this->storage->getAbsolutePath($video->getSrcFilename());

            $metadata = $this->getVideoMetadata($inputPath);
            $video->updateMeta($metadata);
            $this->videoRepository->save($video);

            $this->messageBus->dispatch(new VideoMetadataExtractionFinished($video));
        } catch (\Exception $e) {
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
