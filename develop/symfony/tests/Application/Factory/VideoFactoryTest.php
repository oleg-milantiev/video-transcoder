<?php

declare(strict_types=1);

namespace App\Tests\Application\Factory;

use App\Application\Factory\VideoFactory;
use App\Domain\Shared\ValueObject\Uuid;
use PHPUnit\Framework\TestCase;

final class VideoFactoryTest extends TestCase
{
    public function testFromFilenameExtractsTitleAndExtension(): void
    {
        $factory = new VideoFactory();
        $userId = Uuid::fromString('00000000-0000-4000-8000-000000000042');

        $video = $factory->fromFilename('My Holiday Clip.mp4', $userId);

        $this->assertSame('My Holiday Clip', $video->title()->value());
        $this->assertSame('mp4', $video->extension()->value());
        $this->assertSame('00000000-0000-4000-8000-000000000042', $video->userId()->toRfc4122());
    }

    public function testFromFilenameHandlesNameWithoutExtension(): void
    {
        $factory = new VideoFactory();
        $userId = Uuid::fromString('00000000-0000-4000-8000-000000000007');

        $video = $factory->fromFilename('server-name.mkv', $userId);

        $this->assertSame('server-name', $video->title()->value());
        $this->assertSame('mkv', $video->extension()->value());
        $this->assertSame('00000000-0000-4000-8000-000000000007', $video->userId()->toRfc4122());
    }
}
