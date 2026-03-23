<?php

declare(strict_types=1);

namespace App\Domain\Video\ValueObject;

enum RealtimeNotificationLevel: string
{
    case SUCCESS = 'success';
    case INFO = 'info';
    case WARNING = 'warning';
    case ERROR = 'error';
}
