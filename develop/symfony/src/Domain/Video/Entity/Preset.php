<?php
declare(strict_types=1);

namespace App\Domain\Video\Entity;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Entity\Tariff;
use App\Domain\Video\ValueObject\AudioCodec;
use App\Domain\Video\ValueObject\Format;
use App\Domain\Video\ValueObject\VideoCodec;

class Preset
{
    private ?Uuid $id;
    private VideoCodec $videoCodec;
    private AudioCodec $audioCodec;
    private Format $format;
    /** @var Tariff[] */
    private array $tariffs;

    public function __construct(
        VideoCodec $videoCodec,
        AudioCodec $audioCodec,
        Format $format,
        ?Uuid $id = null,
        array $tariffs = [],
    ) {
        $this->id = $id;
        $this->tariffs = $tariffs;
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

    public function tariffs(): array
    {
        return $this->tariffs;
    }

    public static function create(
        VideoCodec $videoCodec,
        AudioCodec $audioCodec,
        Format $format,
    ): self {
        return new self($videoCodec, $audioCodec, $format);
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
