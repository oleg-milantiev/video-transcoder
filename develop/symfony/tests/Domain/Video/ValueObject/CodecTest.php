<?php

declare(strict_types=1);

namespace App\Tests\Domain\Video\ValueObject;

use App\Domain\Video\ValueObject\Codec;
use App\Domain\Video\Exception\UnsupportedCodec;
use PHPUnit\Framework\TestCase;

/**
 * Tests Codec — нормализация (lowercase + trim), валидация допустимых кодеков, isAv1(), equals().
 */
final class CodecTest extends TestCase
{
    /** Допустимый кодек сохраняется; __toString() совпадает с value(). */
    public function testValidCodec(): void
    {
        $codec = new Codec('h264');
        $this->assertSame('h264', $codec->value());
        $this->assertSame('h264', (string) $codec);
    }

    /** Кодек нормализуется (uppercase → lowercase, trim). */
    public function testCodecIsNormalized(): void
    {
        $codec = new Codec(' H265 ');
        $this->assertSame('h265', $codec->value());
    }

    /** Неизвестный кодек бросает UnsupportedCodec. */
    public function testInvalidCodecThrowsException(): void
    {
        $this->expectException(UnsupportedCodec::class);
        new Codec('unsupported');
    }

    /** isAv1() возвращает true только для 'av1'. */
    public function testIsAv1(): void
    {
        $this->assertTrue(new Codec('av1')->isAv1());
        $this->assertFalse(new Codec('h264')->isAv1());
    }

    /** Два объекта с одинаковым кодеком равны; с разными — нет. */
    public function testEquals(): void
    {
        $a = new Codec('h264');
        $b = new Codec('h264');
        $c = new Codec('vp9');
        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
