<?php

declare(strict_types=1);

namespace App\Tests\Domain\Video\ValueObject;

use App\Domain\Video\ValueObject\Resolution;
use App\Domain\Video\Exception\IncompatibleVideoFormat;
use PHPUnit\Framework\TestCase;

/**
 * Tests Resolution — разрешение видео (width × height); is4k(), equals(), __toString().
 */
final class ResolutionTest extends TestCase
{
    /** Допустимое разрешение сохраняется; геттеры возвращают корректные значения. */
    public function testValidResolution(): void
    {
        $res = new Resolution(1920, 1080);
        $this->assertSame(1920, $res->width());
        $this->assertSame(1080, $res->height());
    }

    /** Отрицательная ширина бросает IncompatibleVideoFormat. */
    public function testNegativeWidthThrows(): void
    {
        $this->expectException(IncompatibleVideoFormat::class);
        new Resolution(-1, 1080);
    }

    /** Нулевая высота бросает IncompatibleVideoFormat. */
    public function testZeroHeightThrows(): void
    {
        $this->expectException(IncompatibleVideoFormat::class);
        new Resolution(1920, 0);
    }

    /** is4k() возвращает true если width ≥ 3840 ИЛИ height ≥ 2160. */
    public function testIs4kReturnsTrueFor4kWidth(): void
    {
        $this->assertTrue((new Resolution(3840, 2160))->is4k());
        $this->assertTrue((new Resolution(4096, 1080))->is4k());
        $this->assertTrue((new Resolution(1920, 2160))->is4k());
        $this->assertFalse((new Resolution(1920, 1080))->is4k());
    }

    /** Два объекта с одинаковым разрешением равны; с разными — нет. */
    public function testEquals(): void
    {
        $a = new Resolution(1920, 1080);
        $b = new Resolution(1920, 1080);
        $c = new Resolution(1280, 720);
        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    /** __toString() возвращает строку формата WIDTHxHEIGHT. */
    public function testToString(): void
    {
        $res = new Resolution(1920, 1080);
        $this->assertSame('1920x1080', (string) $res);
    }
}
