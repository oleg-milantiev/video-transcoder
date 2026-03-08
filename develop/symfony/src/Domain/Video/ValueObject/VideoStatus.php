<?php

namespace App\Domain\Video\ValueObject;

enum VideoStatus: string
{
    case PENDING = 'pending';
    case UPLOADING = 'uploading';
    case READY = 'ready';
    case FAILED = 'failed';

    public function value(): string
    {
        return $this->value;
    }
}
