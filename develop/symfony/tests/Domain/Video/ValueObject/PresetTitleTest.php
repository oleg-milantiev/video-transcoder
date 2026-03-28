<?php

declare(strict_types=1);

namespace Tests\Domain\Video\ValueObject;

use App\Domain\Video\Exception\InvalidPresetTitle;
use App\Domain\Video\ValueObject\PresetTitle;
use PHPUnit\Framework\TestCase;

class PresetTitleTest extends TestCase
{
    public function testValue(): void
    {
        $title = new PresetTitle('HD 720p');
        $this->assertSame('HD 720p', $title->value());
        $this->assertSame('HD 720p', (string) $title);
    }

    public function testTrimmed(): void
    {
        $title = new PresetTitle('  4K Ultra  ');
        $this->assertSame('4K Ultra', $title->value());
    }

    public function testTooShortThrows(): void
    {
        $this->expectException(InvalidPresetTitle::class);
        new PresetTitle('HD');
    }

    public function testTooLongThrows(): void
    {
        $this->expectException(InvalidPresetTitle::class);
        new PresetTitle(str_repeat('a', 256));
    }

    public function testEquals(): void
    {
        $a = new PresetTitle('HD 720p');
        $b = new PresetTitle('HD 720p');
        $c = new PresetTitle('4K Ultra');
        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
