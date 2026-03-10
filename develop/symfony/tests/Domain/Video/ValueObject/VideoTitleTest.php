<?php

declare(strict_types=1);

namespace Tests\Domain\Video\ValueObject;

use App\Domain\Video\ValueObject\VideoTitle;
use PHPUnit\Framework\TestCase;

class VideoTitleTest extends TestCase
{
    public function testValidTitle(): void
    {
        $title = new VideoTitle('Test Video');
        $this->assertSame('Test Video', $title->value());
        $this->assertSame('Test Video', (string)$title);
    }

    public function testTrimmed(): void
    {
        $title = new VideoTitle('  Hello  ');
        $this->assertSame('Hello', $title->value());
    }

    public function testEmptyThrows(): void
    {
        $this->expectException(\DomainException::class);
        new VideoTitle('');
    }

    public function testTooLongThrows(): void
    {
        $this->expectException(\DomainException::class);
        new VideoTitle(str_repeat('a', 256));
    }
}

