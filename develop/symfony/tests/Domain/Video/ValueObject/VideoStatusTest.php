<?php

declare(strict_types=1);

namespace Tests\Domain\Video\ValueObject;

use App\Domain\Video\ValueObject\VideoStatus;
use PHPUnit\Framework\TestCase;

class VideoStatusTest extends TestCase
{
    public function testEnumCases(): void
    {
        $this->assertSame('UPLOADING', VideoStatus::UPLOADING->name);
        $this->assertSame('UPLOADED', VideoStatus::UPLOADED->name);
    }

    public function testFromValue(): void
    {
        $this->assertSame(VideoStatus::UPLOADING, VideoStatus::from(VideoStatus::UPLOADING->value));
    }

    public function testNamesConstant(): void
    {
        $this->assertArrayHasKey(VideoStatus::UPLOADING->value, VideoStatus::NAMES);
        $this->assertArrayHasKey(VideoStatus::UPLOADED->value, VideoStatus::NAMES);
    }
}

