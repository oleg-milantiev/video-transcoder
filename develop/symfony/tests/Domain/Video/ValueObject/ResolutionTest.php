<?php

declare(strict_types=1);

namespace Tests\Domain\Video\ValueObject;

use App\Domain\Video\ValueObject\Resolution;
use App\Domain\Video\Exception\IncompatibleVideoFormat;
use PHPUnit\Framework\TestCase;

class ResolutionTest extends TestCase
{
    public function testValidResolution(): void
    {
        $res = new Resolution(1920, 1080);
        $this->assertSame(1920, $res->width());
        $this->assertSame(1080, $res->height());
    }

    public function testNegativeWidthThrows(): void
    {
        $this->expectException(IncompatibleVideoFormat::class);
        new Resolution(-1, 1080);
    }

    public function testZeroHeightThrows(): void
    {
        $this->expectException(IncompatibleVideoFormat::class);
        new Resolution(1920, 0);
    }

    public function testIs4kReturnsTrueFor4kWidth(): void
    {
        $this->assertTrue((new Resolution(3840, 2160))->is4k());
        $this->assertTrue((new Resolution(4096, 1080))->is4k());
        $this->assertTrue((new Resolution(1920, 2160))->is4k());
        $this->assertFalse((new Resolution(1920, 1080))->is4k());
    }

    public function testEquals(): void
    {
        $a = new Resolution(1920, 1080);
        $b = new Resolution(1920, 1080);
        $c = new Resolution(1280, 720);
        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    public function testToString(): void
    {
        $res = new Resolution(1920, 1080);
        $this->assertSame('1920x1080', (string) $res);
    }
}

