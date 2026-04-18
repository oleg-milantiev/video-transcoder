<?php
declare(strict_types=1);

namespace App\Domain\Video\Entity;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\ValueObject\AudioCodec;
use App\Domain\Video\ValueObject\Format;
use App\Domain\Video\ValueObject\PresetTitle;
use App\Domain\Video\ValueObject\VideoCodec;

class Preset
{
    private ?Uuid $id;
    private PresetTitle $title;
    private VideoCodec $videoCodec;
    private AudioCodec $audioCodec;
    private Format $format;

    public function __construct(
        PresetTitle $title,
        VideoCodec $videoCodec,
        AudioCodec $audioCodec,
        Format $format,
        ?Uuid $id = null,
    ) {
        $this->id = $id;
        $this->rename($title);
        $this->changeOutput($videoCodec, $audioCodec, $format);
    }

    public function id(): ?Uuid
    {
        return $this->id;
    }

    public function title(): PresetTitle
    {
        return $this->title;
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

    public static function create(
        PresetTitle $title,
        VideoCodec $videoCodec,
        AudioCodec $audioCodec,
        Format $format,
    ): self {
        return new self($title, $videoCodec, $audioCodec, $format);
    }

    public function rename(PresetTitle $title): void
    {
        $this->title = $title;
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
}
