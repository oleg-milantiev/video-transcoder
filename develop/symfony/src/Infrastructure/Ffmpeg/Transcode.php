<?php
declare(strict_types=1);

namespace App\Infrastructure\Ffmpeg;

use App\Domain\Video\Entity\Preset;
use App\Domain\Video\ValueObject\Bitrate;
use App\Domain\Video\ValueObject\Codec;

readonly class Transcode
{
    public static function buildCommand(string $inputPath, string $outputPath, Preset $preset): array
    {
        $resolution = $preset->resolution();
        $codec = $preset->codec();
        $bitrate = $preset->bitrate();

        return [
            'ffmpeg',
            '-y',
            '-i', $inputPath,
            '-vf', sprintf('scale=%d:%d', $resolution->width(), $resolution->height()),
            '-c:v', self::mapCodec($codec),
            '-b:v', self::formatBitrate($bitrate),
            '-preset', 'medium',
            '-movflags', '+faststart',
            '-c:a', 'aac',
            '-b:a', '128k',
            '-progress', 'pipe:2',
            '-nostats',
            $outputPath,
        ];
    }

    private static function mapCodec(Codec $codec): string
    {
        return match ($codec->value()) {
            'h265' => 'libx265',
            'vp9' => 'libvpx-vp9',
            'av1' => 'libaom-av1',
            default => 'libx264',
        };
    }

    private static function formatBitrate(Bitrate $bitrate): string
    {
        $kbps = max(100, (int) round($bitrate->value() * 1000));
        return $kbps . 'k';
    }
}
