<?php

namespace App\Infrastructure\Ffmpeg;

final readonly class VideoMetadataExtractor
{
    public function __construct(
        private ProcessRunnerInterface $processRunner,
    ) {
    }

    /**
     * @return array<string, int|float|string>
     */
    public function extract(string $path): array
    {
        $output = $this->processRunner->mustRunAndGetOutput(self::buildCommand($path));

        return self::parseOutput($output);
    }

    /**
     * @return list<string>
     */
    public static function buildCommand(string $path): array
    {
        return [
            'ffprobe',
            '-v', 'quiet',
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
            $path,
        ];
    }

    /**
     * @return array<string, int|float|string>
     */
    public static function parseOutput(string $output): array
    {
        $decoded = json_decode($output, true);

        if ($decoded === null) {
            throw new \RuntimeException('Failed to parse ffprobe output.');
        }

        $videoStream = null;
        foreach ($decoded['streams'] ?? [] as $stream) {
            if (($stream['codec_type'] ?? '') === 'video') {
                $videoStream = $stream;
                break;
            }
        }

        $metadata = [
            'duration' => (float) ($decoded['format']['duration'] ?? 0.0),
            'bitrate' => (int) ($decoded['format']['bit_rate'] ?? 0),
            'format' => $decoded['format']['format_name'] ?? 'unknown',
            'size' => (int) ($decoded['format']['size'] ?? 0),
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

