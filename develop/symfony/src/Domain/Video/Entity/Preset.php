<?php
declare(strict_types=1);

namespace App\Domain\Video\Entity;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\ValueObject\AudioCodec;
use App\Domain\Video\ValueObject\Bitrate;
use App\Domain\Video\ValueObject\Format;
use App\Domain\Video\ValueObject\PresetTitle;
use App\Domain\Video\ValueObject\Resolution;
use App\Domain\Video\ValueObject\VideoCodec;

class Preset
{
    private ?Uuid $id;
    private PresetTitle $title;
    private Resolution $resolution;
    private VideoCodec $videoCodec;
    private Bitrate $bitrate;
    private AudioCodec $audioCodec;
    private Format $format;

    public function __construct(
        PresetTitle $title,
        Resolution $resolution,
        VideoCodec $videoCodec,
        Bitrate $bitrate,
        AudioCodec $audioCodec,
        Format $format,
        ?Uuid $id = null,
    ) {
        $this->id = $id;
        $this->rename($title);
        $this->changeOutput($resolution, $videoCodec, $bitrate, $audioCodec, $format);
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

    public function videoCodec(): VideoCodec
    {
        return $this->videoCodec;
    }

    public function bitrate(): Bitrate
    {
        return $this->bitrate;
    }

    public function audioCodec(): AudioCodec
    {
        return $this->audioCodec;
    }

    public function format(): Format
    {
        return $this->format;
    }

    public static function create(
        PresetTitle $title,
        Resolution $resolution,
        VideoCodec $videoCodec,
        Bitrate $bitrate,
        AudioCodec $audioCodec,
        Format $format,
    ): self {
        return new self($title, $resolution, $videoCodec, $bitrate, $audioCodec, $format);
    }

    public function rename(PresetTitle $title): void
    {
        $this->title = $title;
    }

    public function changeOutput(
        Resolution $resolution,
        VideoCodec $videoCodec,
        Bitrate $bitrate,
        AudioCodec $audioCodec,
        Format $format,
    ): void {
        $this->assertCompatible($resolution, $videoCodec, $bitrate);

        $this->resolution = $resolution;
        $this->videoCodec = $videoCodec;
        $this->bitrate = $bitrate;
        $this->audioCodec = $audioCodec;
        $this->format = $format;
    }

    private function assertCompatible(
        Resolution $resolution,
        VideoCodec $videoCodec,
        Bitrate $bitrate,
    ): void {
        if ($resolution->is4k() && $bitrate->value() < 8.0) {
            throw new \DomainException('Bitrate is too low for 4K resolution.');
        }

        if ($videoCodec->isAv1() && $bitrate->value() < 1.0) {
            throw new \DomainException('Bitrate is too low for AV1 preset.');
        }
    }
}
