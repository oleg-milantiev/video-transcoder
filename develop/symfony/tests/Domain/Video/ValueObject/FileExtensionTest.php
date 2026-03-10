<?php

declare(strict_types=1);

namespace Tests\Domain\Video\ValueObject;

use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\Exception\IncompatibleVideoFormat;
use PHPUnit\Framework\TestCase;

class FileExtensionTest extends TestCase
{
    public function testValidExtension(): void
    {
        $ext = new FileExtension('mp4');
        $this->assertSame('mp4', $ext->value());
        $this->assertSame('mp4', (string)$ext);
    }

    public function testExtensionIsNormalized(): void
    {
        $ext = new FileExtension(' MKV ');
        $this->assertSame('mkv', $ext->value());
    }

    public function testInvalidExtensionThrowsException(): void
    {
        $this->expectException(IncompatibleVideoFormat::class);
        new FileExtension('exe');
    }
}

