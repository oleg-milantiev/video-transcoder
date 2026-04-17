<?php

declare(strict_types=1);

namespace App\Tests\Domain\Video\ValueObject;

use App\Domain\Video\Exception\InvalidPresetTitle;
use App\Domain\Video\ValueObject\PresetTitle;
use PHPUnit\Framework\TestCase;

/**
 * Tests PresetTitle — отображаемое название пресета; trim, ограничения длины, equals().
 */
final class PresetTitleTest extends TestCase
{
    /** Допустимый заголовок сохраняется; __toString() совпадает с value(). */
    public function testValue(): void
    {
        $title = new PresetTitle('HD 720p');
        $this->assertSame('HD 720p', $title->value());
        $this->assertSame('HD 720p', (string) $title);
    }

    /** Пробелы вокруг заголовка обрезаются. */
    public function testTrimmed(): void
    {
        $title = new PresetTitle('  4K Ultra  ');
        $this->assertSame('4K Ultra', $title->value());
    }

    /** Заголовок короче 3 символов бросает InvalidPresetTitle. */
    public function testTooShortThrows(): void
    {
        $this->expectException(InvalidPresetTitle::class);
        new PresetTitle('HD');
    }

    /** Заголовок длиннее 255 символов бросает InvalidPresetTitle. */
    public function testTooLongThrows(): void
    {
        $this->expectException(InvalidPresetTitle::class);
        new PresetTitle(str_repeat('a', 256));
    }

    /** Два объекта с одинаковым заголовком равны; с разными — нет. */
    public function testEquals(): void
    {
        $a = new PresetTitle('HD 720p');
        $b = new PresetTitle('HD 720p');
        $c = new PresetTitle('4K Ultra');
        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
