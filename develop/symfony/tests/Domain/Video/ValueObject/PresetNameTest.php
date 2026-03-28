<?php

declare(strict_types=1);

namespace Tests\Domain\Video\ValueObject;

use App\Domain\Video\ValueObject\PresetName;
use App\Domain\Video\Exception\InvalidPresetName;
use PHPUnit\Framework\TestCase;

class PresetNameTest extends TestCase
{
    public function testValue(): void
    {
        $name = new PresetName('HD1');
        $this->assertSame('HD1', $name->value());
        $this->assertSame('HD1', (string)$name);
    }

    public function testTrimmed(): void
    {
        $name = new PresetName(' 4K1 ');
        $this->assertSame('4K1', $name->value());
    }

    public function testTooShortThrows(): void
    {
        $this->expectException(InvalidPresetName::class);
        new PresetName('HD');
    }

    public function testTooLongThrows(): void
    {
        $this->expectException(InvalidPresetName::class);
        new PresetName(str_repeat('a', 256));
    }

    public function testEquals(): void
    {
        $a = new PresetName('HD1');
        $b = new PresetName('HD1');
        $c = new PresetName('4K2');
        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
