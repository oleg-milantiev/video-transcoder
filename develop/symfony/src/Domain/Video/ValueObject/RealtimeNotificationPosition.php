<?php

declare(strict_types=1);

namespace App\Domain\Video\ValueObject;

enum RealtimeNotificationPosition: string
{
    case TOP = 'top';
    case TOP_START = 'top-start';
    case TOP_END = 'top-end';
    case CENTER = 'center';
    case CENTER_START = 'center-start';
    case CENTER_END = 'center-end';
    case BOTTOM = 'bottom';
    case BOTTOM_START = 'bottom-start';
    case BOTTOM_END = 'bottom-end';
}
