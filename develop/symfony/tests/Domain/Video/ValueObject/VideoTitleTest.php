<?php

declare(strict_types=1);

namespace App\Tests\Domain\Video\ValueObject;

use App\Domain\Video\Exception\InvalidVideoTitle;
use App\Domain\Video\ValueObject\VideoTitle;
use PHPUnit\Framework\TestCase;

/**
 * Tests VideoTitle — название видео; trim, ограничения длины (≥ 1, < 255), __toString().
 */
final class VideoTitleTest extends TestCase
{
    /** Допустимое название сохраняется; __toString() совпадает с value(). */
    public function testValidTitle(): void
    {
        $title = new VideoTitle('Test Video');
        $this->assertSame('Test Video', $title->value());
        $this->assertSame('Test Video', (string) $title);
    }

    /** Пробелы вокруг названия обрезаются. */
    public function testTrimmed(): void
    {
        $title = new VideoTitle('  Hello  ');
        $this->assertSame('Hello', $title->value());
    }

    /** Пустая (пробельная) строка бросает InvalidVideoTitle. */
    public function testEmptyThrows(): void
    {
        $this->expectException(InvalidVideoTitle::class);
        new VideoTitle('');
    }

    /** Название длиннее 255 символов бросает InvalidVideoTitle. */
    public function testTooLongThrows(): void
    {
        $this->expectException(InvalidVideoTitle::class);
        new VideoTitle(str_repeat('a', 256));
    }
}
