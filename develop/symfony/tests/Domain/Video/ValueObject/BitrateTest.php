<?php

declare(strict_types=1);

namespace Tests\Domain\Video\ValueObject;

use App\Domain\Video\ValueObject\Bitrate;
use App\Domain\Video\Exception\IncompatibleVideoFormat;
use PHPUnit\Framework\TestCase;

class BitrateTest extends TestCase
{
    public function testValidBitrate(): void
    {
        $bitrate = new Bitrate(150.5);
        $this->assertSame(150.5, $bitrate->value());
        $this->assertSame('150.5', (string)$bitrate);
    }

    public function testNegativeBitrateThrowsException(): void
    {
        $this->expectException(IncompatibleVideoFormat::class);
        new Bitrate(-1);
    }

    public function testTooHighBitrateThrowsException(): void
    {
        $this->expectException(IncompatibleVideoFormat::class);
        new Bitrate(250.0);
    }

    public function testEquals(): void
    {
        $a = new Bitrate(120.0);
        $b = new Bitrate(120.0);
        $c = new Bitrate(180.0);
        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    public function testZeroBitrateIsValid(): void
    {
        $bitrate = new Bitrate(0.0);
        $this->assertSame(0.0, $bitrate->value());
    }

    public function testExactMaximumBitrateIsValid(): void
    {
        $bitrate = new Bitrate(200.0);
        $this->assertSame(200.0, $bitrate->value());
    }

    public function testJustAboveMaximumThrows(): void
    {
        $this->expectException(IncompatibleVideoFormat::class);
        new Bitrate(200.001);
    }
}
