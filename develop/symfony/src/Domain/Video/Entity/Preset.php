<?php

namespace App\Domain\Video\Entity;

use App\Domain\Video\ValueObject\Bitrate;
use App\Domain\Video\ValueObject\Codec;
use App\Domain\Video\ValueObject\PresetName;
use App\Domain\Video\ValueObject\Resolution;

class Preset
{
    private ?int $id;
    private PresetName $name;
    private Resolution $resolution;
    private Codec $codec;
    private Bitrate $bitrate;

    // TODO DDD подумать: PresetName и VideoFormat (resolution + codec + bitrate)
    public function __construct(
        PresetName $name,
        Resolution $resolution,
        Codec $codec,
        Bitrate $bitrate,
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

    public function name(): PresetName
    {
        return $this->name;
    }

    public function resolution(): Resolution
    {
        return $this->resolution;
    }

    public function codec(): Codec
    {
        return $this->codec;
    }

    public function bitrate(): Bitrate
    {
        return $this->bitrate;
    }

    public static function create(
        PresetName $name,
        Resolution $resolution,
        Codec $codec,
        Bitrate $bitrate,
    ): self {
        return new self($name, $resolution, $codec, $bitrate);
    }

    public function rename(PresetName $name): void
    {
        $this->name = $name;
    }

    public function changeOutput(
        Resolution $resolution,
        Codec $codec,
        Bitrate $bitrate,
    ): void {
        $this->assertCompatible($resolution, $codec, $bitrate);

        $this->resolution = $resolution;
        $this->codec = $codec;
        $this->bitrate = $bitrate;
    }

    private function assertCompatible(
        Resolution $resolution,
        Codec $codec,
        Bitrate $bitrate,
    ): void {
        if ($resolution->is4k() && $bitrate->value() < 8000) {
            throw new \DomainException('Bitrate is too low for 4K resolution.');
        }

        if ($codec->isAv1() && $bitrate->value() < 1000) {
            throw new \DomainException('Bitrate is too low for AV1 preset.');
        }
    }
}
