<?php
declare(strict_types=1);

namespace App\Application\Helper;

class HumanReadableHelper
{
    public static function formatDateExpired(\DateTimeImmutable $date, ?\DateTimeImmutable $now = null): string
    {
        if ($now === null) {
            $now = new \DateTimeImmutable();
        }
        $dateInterval = $date->diff($now);

        return $date > $now
            ? ($date->sub(new \DateInterval('PT1H')) < $now
                ? 'in less than an hour'
                : ($date->sub(new \DateInterval('PT24H')) < $now
                    ? $dateInterval->format('in %h hours')
                    : $dateInterval->format('in %a days %h hours')))
            : 'expired';
    }

    public static function formatDuration(int|float $duration): string
    {
        $hours = (int)floor($duration / 3600);
        $minutes = (int)floor(fmod($duration, 3600) / 60);
        $seconds = (int)round(fmod($duration, 60));

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    public static function formatFileSize(int $size): string
    {
        return number_format($size / 1024 / 1024, 2) . ' MB';
    }

    public static function formatBitrate(int $size): string
    {
        return number_format($size / 1024 / 1024, 2) . ' Mbps';
    }
}
