<?php
declare(strict_types=1);

namespace App\Tests\Domain\Video\ValueObject;

use App\Domain\Video\Exception\InvalidPresetBitrate;
use App\Domain\Video\ValueObject\PresetBitrate;
use PHPUnit\Framework\TestCase;

final class PresetBitrateTest extends TestCase
{
    public function testConstructWithValidRates(): void
    {
        $bitrate = new PresetBitrate([720 => 5.0, 1080 => 8.0]);

        $this->assertSame([720 => 5.0, 1080 => 8.0], $bitrate->value());
    }

    public function testBitrateForHeightReturnsCorrectValue(): void
    {
        $bitrate = new PresetBitrate([720 => 5.0, 1080 => 8.0]);

        $this->assertSame(5.0, $bitrate->bitrateForHeight(720));
        $this->assertSame(8.0, $bitrate->bitrateForHeight(1080));
        $this->assertNull($bitrate->bitrateForHeight(480));
    }

    public function testIsEmptyReturnsTrueForEmptyArray(): void
    {
        $bitrate = new PresetBitrate([]);

        $this->assertTrue($bitrate->isEmpty());
    }

    public function testIsEmptyReturnsFalseForNonEmptyArray(): void
    {
        $bitrate = new PresetBitrate([720 => 5.0]);

        $this->assertFalse($bitrate->isEmpty());
    }

    public function testFromRawNormalizesStringKeysToIntegers(): void
    {
        $bitrate = PresetBitrate::fromRaw(['720' => 5.0, '1080' => '8.5']);

        $this->assertSame([720 => 5.0, 1080 => 8.5], $bitrate->value());
    }

    public function testToJsonArrayReturnsStringKeys(): void
    {
        $bitrate = new PresetBitrate([720 => 5.0, 1080 => 8.0]);

        $this->assertSame(['720' => 5.0, '1080' => 8.0], $bitrate->toJsonArray());
    }

    public function testDefaultReturnsStandardBitrates(): void
    {
        $bitrate = PresetBitrate::default();

        $expected = [
            144  => 0.1,
            240  => 0.4,
            360  => 1.0,
            480  => 2.5,
            720  => 5.0,
            1080 => 8.0,
            1440 => 16.0,
            2160 => 35.0,
            4320 => 85.0,
        ];

        $this->assertSame($expected, $bitrate->value());
    }

    public function testConstructThrowsForInvalidHeight(): void
    {
        $this->expectException(InvalidPresetBitrate::class);
        $this->expectExceptionMessage('must be a positive integer height');

        new PresetBitrate([0 => 5.0]);
    }

    public function testConstructThrowsForNegativeHeight(): void
    {
        $this->expectException(InvalidPresetBitrate::class);

        new PresetBitrate([-720 => 5.0]);
    }

    public function testConstructThrowsForStringHeight(): void
    {
        $this->expectException(InvalidPresetBitrate::class);

        new PresetBitrate(['abc' => 5.0]);
    }

    public function testConstructThrowsForInvalidBitrateType(): void
    {
        $this->expectException(InvalidPresetBitrate::class);
        $this->expectExceptionMessage('must be a number');

        new PresetBitrate([720 => 'invalid']);
    }

    public function testConstructThrowsForNegativeBitrate(): void
    {
        $this->expectException(InvalidPresetBitrate::class);
        $this->expectExceptionMessage('must be >= 0');

        new PresetBitrate([720 => -5.0]);
    }

    public function testConstructAcceptsIntegerBitrate(): void
    {
        $bitrate = new PresetBitrate([720 => 5]);

        $this->assertSame([720 => 5.0], $bitrate->value());
    }

    public function testConstructAcceptsZeroBitrate(): void
    {
        $bitrate = new PresetBitrate([720 => 0.0]);

        $this->assertSame([720 => 0.0], $bitrate->value());
    }
}
