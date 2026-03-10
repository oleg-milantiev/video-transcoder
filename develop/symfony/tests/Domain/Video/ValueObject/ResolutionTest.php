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
}

