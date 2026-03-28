<?php

declare(strict_types=1);

namespace App\Tests\Domain\Video\Entity;

use App\Domain\Video\Entity\Task;
use App\Domain\Video\Exception\VideoAlreadyDeleted;
use App\Domain\Video\Exception\VideoHasTranscodingTasks;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoTitle;
use PHPUnit\Framework\TestCase;
use App\Domain\Shared\ValueObject\Uuid;

final class VideoTest extends TestCase
{
    public function testCreateInitializesAllFields(): void
    {
        $id = Uuid::fromString('99999999-9999-4999-8999-999999999999');
        $createdAt = new \DateTimeImmutable('2026-03-19 12:00:00');

        $video = Video::reconstitute(
            title: new VideoTitle('Trailer'),
            extension: new FileExtension('mp4'),
            userId: Uuid::fromString('77777777-7777-4777-8777-777777777777'),
            meta: ['duration' => 11.5],
            dates: VideoDates::create($createdAt),
            id: $id,
        );

        $this->assertSame($id, $video->id());
        $this->assertSame('Trailer', $video->title()->value());
        $this->assertSame('mp4', $video->extension()->value());
        $this->assertSame('77777777-7777-4777-8777-777777777777', $video->userId()->toRfc4122());
        $this->assertSame(11.5, $video->duration());
        $this->assertSame($createdAt, $video->createdAt());
        $this->assertNull($video->updatedAt());
    }

    public function testCreateInitializesWithoutIdAndWithDates(): void
    {
        $video = Video::create(
            new VideoTitle('No id video'),
            new FileExtension('mov'),
            Uuid::fromString('55555555-5555-4555-8555-555555555550'),
        );

        $this->assertNull($video->id());
        $this->assertNotNull($video->createdAt());
    }

    public function testUpdateMetaMergesTopLevelKeysAndSetsUpdatedAt(): void
    {
        $video = Video::reconstitute(
            title: new VideoTitle('Meta merge'),
            extension: new FileExtension('mkv'),
            userId: Uuid::fromString('22222222-2222-4222-8222-222222222220'),
            meta: ['duration' => 100.2, 'quality' => 'hd'],
            dates: VideoDates::create(new \DateTimeImmutable('2026-03-19 10:00:00')),
            id: Uuid::fromString('11111111-1111-4111-8111-111111111111'),
        );

        $video->updateMeta(['preview' => true]);

        $this->assertSame(100.2, $video->meta()['duration']);
        $this->assertSame('hd', $video->meta()['quality']);
        $this->assertTrue($video->meta()['preview']);
        $this->assertNotNull($video->updatedAt());
    }

    public function testUpdateMetaOverridesExistingTopLevelKey(): void
    {
        $video = Video::reconstitute(
            new VideoTitle('Replace key'),
            new FileExtension('mp4'),
            Uuid::fromString('22222222-2222-4222-8222-222222222221'),
            ['duration' => 50.0],
            VideoDates::create(new \DateTimeImmutable('2026-03-19 10:00:00')),
            Uuid::fromString('22222222-2222-4222-8222-222222222222'),
        );

        $video->updateMeta(['duration' => 55.7]);

        $this->assertSame(55.7, $video->duration());
    }

    public function testMarkDeletedMarksVideoDeletedWhenNoTranscodingTasks(): void
    {
        $video = Video::reconstitute(
            new VideoTitle('Delete me'),
            new FileExtension('mp4'),
            Uuid::fromString('99999999-9999-4999-8999-999999999997'),
            ['preview' => true],
            VideoDates::create(),
            Uuid::fromString('55555555-5555-4555-8555-555555555555'),
        );

        $task = Task::create(
            Uuid::fromString('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'),
            Uuid::fromString('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'),
            Uuid::fromString('cccccccc-cccc-4ccc-8ccc-cccccccccccc'),
        );
        $task->markDeleted();

        $video->markDeleted([$task]);

        $this->assertTrue($video->isDeleted());
        $this->assertTrue(($video->meta()['preview'] ?? false));
    }

    public function testMarkDeletedThrowsWhenVideoAlreadyDeleted(): void
    {
        $video = Video::reconstitute(
            new VideoTitle('Deleted'),
            new FileExtension('mp4'),
            Uuid::fromString('11111111-1111-4111-8111-111111111112'),
            [],
            VideoDates::create(),
            Uuid::fromString('11111111-1111-4111-8111-111111111113'),
            true,
        );

        $this->expectException(VideoAlreadyDeleted::class);
        $video->markDeleted([]);
    }

    public function testMarkDeletedThrowsWhenTranscodingTaskExists(): void
    {
        $video = Video::create(
            new VideoTitle('Protected'),
            new FileExtension('mp4'),
            Uuid::fromString('11111111-1111-4111-8111-111111111114'),
        );

        $task = Task::create(
            Uuid::fromString('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'),
            Uuid::fromString('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'),
            Uuid::fromString('cccccccc-cccc-4ccc-8ccc-cccccccccccc'),
        );

        $this->expectException(VideoHasTranscodingTasks::class);
        $video->markDeleted([$task]);
    }

    public function testClearSourceKeySetsSourceKeyToNull(): void
    {
        $video = Video::reconstitute(
            new VideoTitle('Source video'),
            new FileExtension('mp4'),
            Uuid::fromString('11111111-1111-4111-8111-111111111115'),
            ['sourceKey' => 'videos/original.mp4'],
            VideoDates::create(),
            Uuid::fromString('11111111-1111-4111-8111-111111111116'),
        );

        $video->clearSourceKey();

        $this->assertNull($video->meta()['sourceKey']);
        $this->assertNotNull($video->updatedAt());
    }

    public function testChangeTitleUpdatesTitle(): void
    {
        $video = Video::create(
            new VideoTitle('Old Title'),
            new FileExtension('mp4'),
            Uuid::fromString('11111111-1111-4111-8111-111111111117'),
        );

        $video->changeTitle(new VideoTitle('New Title'));

        $this->assertSame('New Title', $video->title()->value());
        $this->assertNotNull($video->updatedAt());
    }

    public function testChangeTitleOnDeletedVideoThrows(): void
    {
        $video = Video::reconstitute(
            new VideoTitle('Deleted'),
            new FileExtension('mp4'),
            Uuid::fromString('11111111-1111-4111-8111-111111111118'),
            [],
            VideoDates::create(),
            Uuid::fromString('11111111-1111-4111-8111-111111111119'),
            true,
        );

        $this->expectException(VideoAlreadyDeleted::class);
        $video->changeTitle(new VideoTitle('New'));
    }

    public function testUpdateMetaOnDeletedVideoThrows(): void
    {
        $video = Video::reconstitute(
            new VideoTitle('Deleted'),
            new FileExtension('mp4'),
            Uuid::fromString('22222222-2222-4222-8222-222222222223'),
            [],
            VideoDates::create(),
            Uuid::fromString('22222222-2222-4222-8222-222222222224'),
            true,
        );

        $this->expectException(VideoAlreadyDeleted::class);
        $video->updateMeta(['key' => 'value']);
    }

    public function testDurationReturnsNullWhenNotInMeta(): void
    {
        $video = Video::create(
            new VideoTitle('No duration'),
            new FileExtension('mp4'),
            Uuid::fromString('22222222-2222-4222-8222-222222222225'),
        );

        $this->assertNull($video->duration());
    }
}

