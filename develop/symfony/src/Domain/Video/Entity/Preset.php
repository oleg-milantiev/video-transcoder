<?php
declare(strict_types=1);

namespace App\Domain\Video\Entity;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\ValueObject\Bitrate;
use App\Domain\Video\ValueObject\Codec;
use App\Domain\Video\ValueObject\PresetTitle;
use App\Domain\Video\ValueObject\Resolution;

class Preset
{
    private ?Uuid $id;
    private PresetTitle $title;
    private Resolution $resolution;
    private Codec $codec;
    private Bitrate $bitrate;

    public function __construct(
        PresetTitle $title,
        Resolution $resolution,
        Codec $codec,
        Bitrate $bitrate,
        ?Uuid $id = null,
    ) {
        $this->id = $id;
        $this->rename($title);
        $this->changeOutput($resolution, $codec, $bitrate);
    }

    public function id(): ?Uuid
    {
        return $this->id;
    }

    public function title(): PresetTitle
    {
        return $this->title;
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
        PresetTitle $title,
        Resolution $resolution,
        Codec $codec,
        Bitrate $bitrate,
    ): self {
        return new self($title, $resolution, $codec, $bitrate);
    }

    public function rename(PresetTitle $title): void
    {
        $this->title = $title;
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
        if ($resolution->is4k() && $bitrate->value() < 8.0) {
            throw new \DomainException('Bitrate is too low for 4K resolution.');
        }

        if ($codec->isAv1() && $bitrate->value() < 1.0) {
            throw new \DomainException('Bitrate is too low for AV1 preset.');
        }
    }
}
