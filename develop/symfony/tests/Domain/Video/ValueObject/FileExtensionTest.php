<?php

declare(strict_types=1);

namespace App\Tests\Domain\Video\ValueObject;

use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\Exception\IncompatibleVideoFormat;
use PHPUnit\Framework\TestCase;

/**
 * Tests FileExtension — нормализация расширения (lowercase + trim), допустимые форматы, equals().
 */
final class FileExtensionTest extends TestCase
{
    /** Допустимое расширение сохраняется; __toString() совпадает с value(). */
    public function testValidExtension(): void
    {
        $ext = new FileExtension('mp4');
        $this->assertSame('mp4', $ext->value());
        $this->assertSame('mp4', (string) $ext);
    }

    /** Расширение нормализуется (uppercase → lowercase, trim). */
    public function testExtensionIsNormalized(): void
    {
        $ext = new FileExtension(' MKV ');
        $this->assertSame('mkv', $ext->value());
    }

    /** Недопустимое расширение бросает IncompatibleVideoFormat. */
    public function testInvalidExtensionThrowsException(): void
    {
        $this->expectException(IncompatibleVideoFormat::class);
        new FileExtension('exe');
    }

    /** Все допустимые расширения принимаются без ошибок. */
    public function testAllAllowedExtensionsAreAccepted(): void
    {
        foreach (['mp4', 'mkv', 'avi', 'mov', '3gp'] as $ext) {
            $this->assertSame($ext, new FileExtension($ext)->value());
        }
    }

    /** Пустая строка бросает IncompatibleVideoFormat. */
    public function testEmptyStringThrows(): void
    {
        $this->expectException(IncompatibleVideoFormat::class);
        new FileExtension('');
    }
}
