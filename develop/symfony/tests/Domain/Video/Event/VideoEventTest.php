<?php

declare(strict_types=1);

namespace App\Tests\Domain\Video\Event;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Event\VideoCreated;
use App\Domain\Video\Event\VideoMetadataExtractionFinished;
use App\Domain\Video\Event\VideoMetadataExtractionStarted;
use App\Domain\Video\Event\VideoPreviewGenerationFinished;
use App\Domain\Video\Event\VideoPreviewGenerationStarted;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoTitle;
use PHPUnit\Framework\TestCase;

final class VideoEventTest extends TestCase
{
    private function makeVideo(): Video
    {
        return Video::create(
            new VideoTitle('Event test video'),
            new FileExtension('mp4'),
            Uuid::fromString('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'),
        );
    }

    public function testVideoCreatedHoldsVideo(): void
    {
        $video = $this->makeVideo();
        $event = new VideoCreated($video);
        $this->assertSame($video, $event->video());
    }

    public function testVideoMetadataExtractionStartedHoldsVideo(): void
    {
        $video = $this->makeVideo();
        $event = new VideoMetadataExtractionStarted($video);
        $this->assertSame($video, $event->video());
    }

    public function testVideoMetadataExtractionFinishedHoldsVideo(): void
    {
        $video = $this->makeVideo();
        $event = new VideoMetadataExtractionFinished($video);
        $this->assertSame($video, $event->video());
    }

    public function testVideoPreviewGenerationStartedHoldsVideo(): void
    {
        $video = $this->makeVideo();
        $event = new VideoPreviewGenerationStarted($video);
        $this->assertSame($video, $event->video());
    }

    public function testVideoPreviewGenerationFinishedHoldsVideo(): void
    {
        $video = $this->makeVideo();
        $event = new VideoPreviewGenerationFinished($video);
        $this->assertSame($video, $event->video());
    }
}
