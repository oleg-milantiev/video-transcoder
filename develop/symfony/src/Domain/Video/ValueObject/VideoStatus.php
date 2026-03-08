<?php

namespace App\Domain\Video\ValueObject;

enum VideoStatus: string
{
    // TODO move to int as TaskStatus
    case PENDING = 'pending';
    case UPLOADING = 'uploading';
    case READY = 'ready';
    case FAILED = 'failed';

    public function value(): string
    {
        return $this->value;
    }
}
