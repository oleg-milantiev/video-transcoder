<?php
declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Video\Entity\Preset;

readonly class PresetItemDTO
{
    public function __construct(
        public string $id,
        public string $title,
        public string $videoCodec,
        public string $audioCodec,
        public string $format,
        public array $bitrate,
    ) {}

    public static function fromDomain(Preset $preset): self
    {
        return new self(
            id: $preset->id()->toRfc4122(),
            title: $preset->label(),
            videoCodec: $preset->videoCodec()->value(),
            audioCodec: $preset->audioCodec()->value(),
            format: $preset->format()->value(),
            bitrate: $preset->bitrate()->value(),
        );
    }
}
