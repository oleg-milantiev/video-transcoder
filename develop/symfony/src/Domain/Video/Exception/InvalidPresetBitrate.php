<?php
declare(strict_types=1);

namespace App\Domain\Video\Exception;

use DomainException;

final class InvalidPresetBitrate extends DomainException
{
    public static function invalidHeight(mixed $height): self
    {
        return new self(
            sprintf(
                'Preset bitrate key must be a positive integer height, got: %s',
                is_scalar($height) ? var_export($height, true) : gettype($height)
            )
        );
    }

    public static function invalidBitrate(int $height, mixed $bitrate): self
    {
        return new self(
            sprintf(
                'Preset bitrate value for height %d must be a number, got: %s',
                $height,
                is_scalar($bitrate) ? var_export($bitrate, true) : gettype($bitrate)
            )
        );
    }

    public static function negativeBitrate(int $height, float $bitrate): self
    {
        return new self(
            sprintf(
                'Preset bitrate for height %d must be >= 0, got: %f',
                $height,
                $bitrate
            )
        );
    }
}
