<?php

declare(strict_types=1);

namespace App\Tests\Domain\Video\ValueObject;

use App\Domain\Video\ValueObject\PresetName;
use App\Domain\Video\Exception\InvalidPresetName;
use PHPUnit\Framework\TestCase;

/**
 * Tests PresetName — техническое имя пресета; trim, ограничения длины (≥ 3 символа), equals().
 */
final class PresetNameTest extends TestCase
{
    /** Допустимое имя сохраняется; __toString() совпадает с value(). */
    public function testValue(): void
    {
        $name = new PresetName('HD1');
        $this->assertSame('HD1', $name->value());
        $this->assertSame('HD1', (string) $name);
    }

    /** Пробелы вокруг имени обрезаются. */
    public function testTrimmed(): void
    {
        $name = new PresetName(' 4K1 ');
        $this->assertSame('4K1', $name->value());
    }

    /** Имя короче 3 символов бросает InvalidPresetName. */
    public function testTooShortThrows(): void
    {
        $this->expectException(InvalidPresetName::class);
        new PresetName('HD');
    }

    /** Имя длиннее 255 символов бросает InvalidPresetName. */
    public function testTooLongThrows(): void
    {
        $this->expectException(InvalidPresetName::class);
        new PresetName(str_repeat('a', 256));
    }

    /** Два объекта с одинаковым именем равны; с разными — нет. */
    public function testEquals(): void
    {
        $a = new PresetName('HD1');
        $b = new PresetName('HD1');
        $c = new PresetName('4K2');
        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
