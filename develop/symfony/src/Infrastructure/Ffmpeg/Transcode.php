<?php
declare(strict_types=1);

namespace App\Infrastructure\Ffmpeg;

use App\Application\DTO\TranscodeStartContextDTO;
use App\Domain\Video\ValueObject\AudioCodec;
use App\Domain\Video\ValueObject\VideoCodec;

readonly class Transcode
{
    public static function buildCommand(TranscodeStartContextDTO $context): array
    {
        $meta = $context->task->meta();
        if (!isset($meta['width'], $meta['height'], $meta['bitrate'])) {
            throw new \InvalidArgumentException('Missing meta data');
        }

        if ((int) $meta['width'] <= 0 || (int) $meta['height'] <= 0) {
            throw new \InvalidArgumentException('Invalid meta data');
        }

        if ($context->video->meta()['width'] > $context->video->meta()['height']) {
            $width = (int) $meta['width'];
            $height = (int) $meta['height'];
        }
        else {
            $width = (int) $meta['height'];
            $height = (int) $meta['width'];
        }

        return [
            'ffmpeg',
            '-y',
            '-i', $context->inputPath,
            '-vf', sprintf('scale=%d:%d', $width, $height),
            '-c:v', self::mapVideoCodec($context->preset->videoCodec()),
            '-b:v', self::formatBitrate((float) $meta['bitrate']),
            '-preset', 'medium',
            '-movflags', '+faststart',
            '-c:a', self::mapAudioCodec($context->preset->audioCodec()),
            '-b:a', '128k',
            '-progress', 'pipe:2',
            '-nostats',
            $context->absoluteOutputPath,
        ];
    }

    private static function mapVideoCodec(VideoCodec $codec): string
    {
        return match ($codec->value()) {
            'h265' => 'libx265',
            'vp9' => 'libvpx-vp9',
            'av1' => 'libaom-av1',
            default => 'libx264',
        };
    }

    private static function mapAudioCodec(AudioCodec $codec): string
    {
        return match ($codec->value()) {
            'opus' => 'libopus',
            default => 'aac',
        };
    }

    private static function formatBitrate(float $bitrateValue): string
    {
        $kbps = max(100, (int) round($bitrateValue * 1000));
        return $kbps . 'k';
    }
}
