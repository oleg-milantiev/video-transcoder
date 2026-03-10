<?php

declare(strict_types=1);

namespace Tests\Domain\Video\ValueObject;

use App\Domain\Video\ValueObject\Codec;
use App\Domain\Video\Exception\UnsupportedCodec;
use PHPUnit\Framework\TestCase;

class CodecTest extends TestCase
{
    public function testValidCodec(): void
    {
        $codec = new Codec('h264');
        $this->assertSame('h264', $codec->value());
        $this->assertSame('h264', (string)$codec);
    }

    public function testCodecIsNormalized(): void
    {
        $codec = new Codec(' H265 ');
        $this->assertSame('h265', $codec->value());
    }

    public function testInvalidCodecThrowsException(): void
    {
        $this->expectException(UnsupportedCodec::class);
        new Codec('unsupported');
    }

    public function testIsAv1(): void
    {
        $codec = new Codec('av1');
        $this->assertTrue($codec->isAv1());
        $codec = new Codec('h264');
        $this->assertFalse($codec->isAv1());
    }

    public function testEquals(): void
    {
        $a = new Codec('h264');
        $b = new Codec('h264');
        $c = new Codec('vp9');
        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}

