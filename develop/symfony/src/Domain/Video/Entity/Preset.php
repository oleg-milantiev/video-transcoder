<?php

namespace App\Domain\Video\Entity;

use InvalidArgumentException;

class Preset
{
    private ?int $id;
    private string $name;
    private string $resolution;
    private string $codec;
    private int $bitrate;

    public function __construct(
        string $name,
        string $resolution,
        string $codec,
        int $bitrate,
        ?int $id = null,
    ) {
        $this->id = $id;

        $this->rename($name);
        $this->changeOutput($resolution, $codec, $bitrate);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function resolution(): string
    {
        return $this->resolution;
    }

    public function codec(): string
    {
        return $this->codec;
    }

    public function bitrate(): int
    {
        return $this->bitrate;
    }

    public function rename(string $name): void
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('PresetEntity name cannot be empty.');
        }

        if (mb_strlen($name) > 255) {
            throw new InvalidArgumentException('PresetEntity name is too long.');
        }

        $this->name = $name;
    }

    public function changeOutput(string $resolution, string $codec, int $bitrate): void
    {
        $resolution = trim($resolution);
        $codec = mb_strtolower(trim($codec));

        if ($resolution === '') {
            throw new InvalidArgumentException('Resolution cannot be empty.');
        }

        if (!in_array($codec, ['h264', 'h265', 'vp9', 'av1'], true)) {
            throw new InvalidArgumentException(sprintf('Unsupported codec: %s', $codec));
        }

        if ($bitrate <= 0) {
            throw new InvalidArgumentException('Bitrate must be greater than zero.');
        }

        $this->assertCompatible($resolution, $codec, $bitrate);

        $this->resolution = $resolution;
        $this->codec = $codec;
        $this->bitrate = $bitrate;
    }

    private function assertCompatible(string $resolution, string $codec, int $bitrate): void
    {
        if ($resolution === '3840x2160' && $bitrate < 8_000) {
            throw new InvalidArgumentException('Bitrate is too low for 4K resolution.');
        }

        if ($codec === 'av1' && $bitrate < 1_000) {
            throw new InvalidArgumentException('Bitrate is too low for AV1 preset.');
        }
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
