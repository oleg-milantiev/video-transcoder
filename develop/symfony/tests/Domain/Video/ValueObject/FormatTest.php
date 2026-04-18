<?php

declare(strict_types=1);

namespace App\Tests\Domain\Video\ValueObject;

use App\Domain\Video\ValueObject\Format;
use PHPUnit\Framework\TestCase;

/**
 * Tests Format — нормализация, валидация допустимых значений (mp4, webm), equals().
 */
final class FormatTest extends TestCase
{
    /** Допустимый формат сохраняется; __toString() совпадает с value(). */
    public function testValidFormat(): void
    {
        $format = new Format('mp4');
        $this->assertSame('mp4', $format->value());
        $this->assertSame('mp4', (string) $format);
    }

    /** Формат нормализуется (uppercase → lowercase, trim). */
    public function testFormatIsNormalized(): void
    {
        $format = new Format(' WEBM ');
        $this->assertSame('webm', $format->value());
    }

    /** Неизвестный формат бросает DomainException. */
    public function testInvalidFormatThrowsException(): void
    {
        $this->expectException(\DomainException::class);
        new Format('avi');
    }

    /** Оба допустимых значения принимаются. */
    public function testAllAllowedValues(): void
    {
        foreach (['mp4', 'webm'] as $value) {
            $this->assertSame($value, (new Format($value))->value());
        }
    }

    /** equals() работает корректно. */
    public function testEquals(): void
    {
        $a = new Format('mp4');
        $b = new Format('mp4');
        $c = new Format('webm');
        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
