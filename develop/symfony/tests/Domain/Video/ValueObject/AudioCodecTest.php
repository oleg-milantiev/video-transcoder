<?php

declare(strict_types=1);

namespace App\Tests\Domain\Video\ValueObject;

use App\Domain\Video\ValueObject\AudioCodec;
use App\Domain\Video\Exception\UnsupportedCodec;
use PHPUnit\Framework\TestCase;

/**
 * Tests AudioCodec — нормализация, валидация допустимых значений (aac, opus), equals().
 */
final class AudioCodecTest extends TestCase
{
    /** Допустимый кодек сохраняется; __toString() совпадает с value(). */
    public function testValidCodec(): void
    {
        $codec = new AudioCodec('aac');
        $this->assertSame('aac', $codec->value());
        $this->assertSame('aac', (string) $codec);
    }

    /** Кодек нормализуется (uppercase → lowercase, trim). */
    public function testCodecIsNormalized(): void
    {
        $codec = new AudioCodec(' OPUS ');
        $this->assertSame('opus', $codec->value());
    }

    /** Неизвестный кодек бросает UnsupportedCodec. */
    public function testInvalidCodecThrowsException(): void
    {
        $this->expectException(UnsupportedCodec::class);
        new AudioCodec('mp3');
    }

    /** Оба допустимых значения принимаются. */
    public function testAllAllowedValues(): void
    {
        foreach (['aac', 'opus'] as $value) {
            $this->assertSame($value, (new AudioCodec($value))->value());
        }
    }

    /** equals() работает корректно. */
    public function testEquals(): void
    {
        $a = new AudioCodec('aac');
        $b = new AudioCodec('aac');
        $c = new AudioCodec('opus');
        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
