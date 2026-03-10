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
        $bitrate = new Bitrate(1000000);
        $this->assertSame(1000000, $bitrate->value());
        $this->assertSame('1000000', (string)$bitrate);
    }

    public function testNegativeBitrateThrowsException(): void
    {
        $this->expectException(IncompatibleVideoFormat::class);
        new Bitrate(-1);
    }

    public function testTooHighBitrateThrowsException(): void
    {
        $this->expectException(IncompatibleVideoFormat::class);
        new Bitrate(201 * 1024 * 1024);
    }

    public function testEquals(): void
    {
        $a = new Bitrate(1000);
        $b = new Bitrate(1000);
        $c = new Bitrate(2000);
        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}

