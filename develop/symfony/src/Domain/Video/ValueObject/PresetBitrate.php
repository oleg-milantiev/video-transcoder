<?php
declare(strict_types=1);

namespace App\Domain\Video\ValueObject;

use App\Domain\Video\Exception\InvalidPresetBitrate;

final class PresetBitrate
{
    /** @var array<int, float> height => bitrate (Mbps) */
    private array $rates;

    /**
     * @param array<int, float> $rates
     */
    public function __construct(array $rates)
    {
        foreach ($rates as $height => $bitrate) {
            if (!is_int($height) || $height <= 0) {
                throw InvalidPresetBitrate::invalidHeight($height);
            }
            if (!is_float($bitrate) && !is_int($bitrate)) {
                throw InvalidPresetBitrate::invalidBitrate($height, $bitrate);
            }
            if ((float)$bitrate < 0) {
                throw InvalidPresetBitrate::negativeBitrate($height, (float)$bitrate);
            }
        }

        $this->rates = array_map(static fn($v) => (float)$v, $rates);
    }

    /**
     * @param array<string|int, mixed> $raw Raw array from JSON / DB (keys may be strings)
     */
    public static function fromRaw(array $raw): self
    {
        $normalized = [];
        foreach ($raw as $key => $value) {
            $intKey = (int)$key;
            $normalized[$intKey] = (float)$value;
        }

        return new self($normalized);
    }

    public static function default(): self
    {
        return new self([
            144 => 0.1,
            240 => 0.4,
            360 => 1.0,
            480 => 2.5,
            720 => 5.0,
            1080 => 8.0,
            1440 => 16.0,
            2160 => 35.0,
            4320 => 85.0,
        ]);
    }

    /**
     * @return array<int, float>
     */
    public function value(): array
    {
        return $this->rates;
    }

    public function bitrateForHeight(int $height): ?float
    {
        return $this->rates[$height] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->rates === [];
    }

    /**
     * @return array<string, float>  String keys for JSON serialisation
     */
    public function toJsonArray(): array
    {
        $out = [];
        foreach ($this->rates as $height => $bitrate) {
            $out[(string)$height] = $bitrate;
        }

        return $out;
    }
}
