<?php

declare(strict_types=1);

namespace App\Tests\Domain\Video\ValueObject;

use App\Domain\Video\ValueObject\VideoCodec;
use App\Domain\Video\Exception\UnsupportedCodec;
use PHPUnit\Framework\TestCase;

/**
 * Tests VideoCodec — нормализация (lowercase + trim), валидация допустимых кодеков, isAv1(), equals().
 */
final class VideoCodecTest extends TestCase
{
    /** Допустимый кодек сохраняется; __toString() совпадает с value(). */
    public function testValidCodec(): void
    {
        $codec = new VideoCodec('h264');
        $this->assertSame('h264', $codec->value());
        $this->assertSame('h264', (string) $codec);
    }

    /** Кодек нормализуется (uppercase → lowercase, trim). */
    public function testCodecIsNormalized(): void
    {
        $codec = new VideoCodec(' H265 ');
        $this->assertSame('h265', $codec->value());
    }

    /** Неизвестный кодек бросает UnsupportedCodec. */
    public function testInvalidCodecThrowsException(): void
    {
        $this->expectException(UnsupportedCodec::class);
        new VideoCodec('unsupported');
    }

    /** isAv1() возвращает true только для 'av1'. */
    public function testIsAv1(): void
    {
        $this->assertTrue(new VideoCodec('av1')->isAv1());
        $this->assertFalse(new VideoCodec('h264')->isAv1());
    }

    /** Два объекта с одинаковым кодеком равны; с разными — нет. */
    public function testEquals(): void
    {
        $a = new VideoCodec('h264');
        $b = new VideoCodec('h264');
        $c = new VideoCodec('vp9');
        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    /** Все допустимые значения принимаются. */
    public function testAllAllowedValues(): void
    {
        foreach (['h264', 'h265', 'vp8', 'vp9', 'av1'] as $value) {
            $this->assertSame($value, new VideoCodec($value)->value());
        }
    }

    /** vp8 принимается и нормализуется. */
    public function testVp8IsAllowed(): void
    {
        $codec = new VideoCodec(' VP8 ');
        $this->assertSame('vp8', $codec->value());
        $this->assertFalse($codec->isAv1());
    }
}
