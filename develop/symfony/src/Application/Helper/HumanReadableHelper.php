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
}
