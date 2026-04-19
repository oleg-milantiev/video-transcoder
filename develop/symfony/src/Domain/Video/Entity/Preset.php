<?php
declare(strict_types=1);

namespace App\Domain\Video\Entity;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\ValueObject\AudioCodec;
use App\Domain\Video\ValueObject\Format;
use App\Domain\Video\ValueObject\PresetBitrate;
use App\Domain\Video\ValueObject\VideoCodec;

class Preset
{
    private ?Uuid $id;
    private VideoCodec $videoCodec;
    private AudioCodec $audioCodec;
    private Format $format;
    private PresetBitrate $bitrate;

    public function __construct(
        VideoCodec $videoCodec,
        AudioCodec $audioCodec,
        Format $format,
        ?Uuid $id = null,
        ?PresetBitrate $bitrate = null,
    ) {
        $this->id = $id;
        $this->bitrate = $bitrate ?? PresetBitrate::default();
        $this->changeOutput($videoCodec, $audioCodec, $format);
    }

    public function id(): ?Uuid
    {
        return $this->id;
    }

    public function label(): string
    {
        return sprintf('%s/%s/%s', $this->videoCodec->value(), $this->audioCodec->value(), $this->format->value());
    }

    public function videoCodec(): VideoCodec
    {
        return $this->videoCodec;
    }

    public function audioCodec(): AudioCodec
    {
        return $this->audioCodec;
    }

    public function format(): Format
    {
        return $this->format;
    }

    public function bitrate(): PresetBitrate
    {
        return $this->bitrate;
    }

    public static function create(
        VideoCodec $videoCodec,
        AudioCodec $audioCodec,
        Format $format,
        ?PresetBitrate $bitrate = null,
    ): self {
        return new self($videoCodec, $audioCodec, $format, null, $bitrate);
    }

    public function changeOutput(
        VideoCodec $videoCodec,
        AudioCodec $audioCodec,
        Format $format,
    ): void {
        $this->videoCodec = $videoCodec;
        $this->audioCodec = $audioCodec;
        $this->format = $format;
    }

    public function changeBitrate(PresetBitrate $bitrate): void
    {
        $this->bitrate = $bitrate;
    }
}
