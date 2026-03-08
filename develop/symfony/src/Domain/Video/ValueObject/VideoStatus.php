<?php

namespace App\Domain\Video\ValueObject;

enum VideoStatus: int
{
    case UPLOADING = 1;
    case UPLOADED = 2;

    public const array NAMES = [
        self::UPLOADING->value => self::UPLOADING->name,
        self::UPLOADED->value => self::UPLOADED->name,
    ];

    public function value(): string
    {
        return $this->value;
    }
}
