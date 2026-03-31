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
            ? ($dateInterval < new \DateInterval('PT1H')
                ? 'in less than an hour'
                : $dateInterval->format('in %a days %h hours'))
            : 'expired';
    }
}
