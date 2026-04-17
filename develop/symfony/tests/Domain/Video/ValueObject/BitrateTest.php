<?php

declare(strict_types=1);

namespace App\Tests\Domain\Video\ValueObject;

use App\Domain\Video\ValueObject\Bitrate;
use App\Domain\Video\Exception\IncompatibleVideoFormat;
use PHPUnit\Framework\TestCase;

/**
 * Tests Bitrate — битрейт видео в Mbps; диапазон [0, 200], equals(), __toString().
 */
final class BitrateTest extends TestCase
{
    /** Допустимый битрейт сохраняется; __toString() возвращает строковое представление. */
    public function testValidBitrate(): void
    {
        $bitrate = new Bitrate(150.5);
        $this->assertSame(150.5, $bitrate->value());
        $this->assertSame('150.5', (string) $bitrate);
    }

    /** Отрицательный битрейт бросает IncompatibleVideoFormat. */
    public function testNegativeBitrateThrowsException(): void
    {
        $this->expectException(IncompatibleVideoFormat::class);
        new Bitrate(-1);
    }

    /** Битрейт выше максимума бросает IncompatibleVideoFormat. */
    public function testTooHighBitrateThrowsException(): void
    {
        $this->expectException(IncompatibleVideoFormat::class);
        new Bitrate(250.0);
    }

    /** Два объекта с одинаковым значением равны; с разными — нет. */
    public function testEquals(): void
    {
        $a = new Bitrate(120.0);
        $b = new Bitrate(120.0);
        $c = new Bitrate(180.0);
        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    /** Нулевой битрейт допустим (граничное значение). */
    public function testZeroBitrateIsValid(): void
    {
        $bitrate = new Bitrate(0.0);
        $this->assertSame(0.0, $bitrate->value());
    }

    /** Точно максимальное значение 200.0 допустимо. */
    public function testExactMaximumBitrateIsValid(): void
    {
        $bitrate = new Bitrate(200.0);
        $this->assertSame(200.0, $bitrate->value());
    }

    /** Значение 200.001 (выше максимума) бросает IncompatibleVideoFormat. */
    public function testJustAboveMaximumThrows(): void
    {
        $this->expectException(IncompatibleVideoFormat::class);
        new Bitrate(200.001);
    }
}
