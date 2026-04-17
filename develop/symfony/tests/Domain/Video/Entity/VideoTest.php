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

/**
 * Tests Video entity — create/reconstitute, updateMeta, changeTitle, markDeleted, clearSourceKey,
 * size/duration accessors и защита от операций над удалённым видео.
 */
final class VideoTest extends TestCase
{
    /** reconstitute() сохраняет все поля; duration() читается из meta['duration']. */
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

    /** create() устанавливает id = null и автоматически заполняет createdAt. */
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

    /** updateMeta() объединяет новые ключи с существующими и обновляет updatedAt. */
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

    /** updateMeta() перезаписывает существующий ключ новым значением. */
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

    /** markDeleted() помечает видео удалённым, если нет активных задач транскодирования. */
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

    /** markDeleted() на уже удалённом видео бросает VideoAlreadyDeleted. */
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

    /** markDeleted() бросает VideoHasTranscodingTasks, если есть задача в статусе isTranscoding(). */
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

    /** clearSourceKey() обнуляет meta['sourceKey'] и обновляет updatedAt. */
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

    /** changeTitle() обновляет заголовок и устанавливает updatedAt. */
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

    /** changeTitle() на удалённом видео бросает VideoAlreadyDeleted. */
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

    /** updateMeta() на удалённом видео бросает VideoAlreadyDeleted. */
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

    /** duration() возвращает null, если meta['duration'] отсутствует. */
    public function testDurationReturnsNullWhenNotInMeta(): void
    {
        $video = Video::create(
            new VideoTitle('No duration'),
            new FileExtension('mp4'),
            Uuid::fromString('22222222-2222-4222-8222-222222222225'),
        );

        $this->assertNull($video->duration());
    }

    /** size() возвращает значение из meta['size']. */
    public function testSizeReturnsValueFromMeta(): void
    {
        $video = Video::reconstitute(
            new VideoTitle('Sized video'),
            new FileExtension('mp4'),
            Uuid::fromString('22222222-2222-4222-8222-222222222226'),
            ['size' => 104857600],
            VideoDates::create(),
            Uuid::fromString('22222222-2222-4222-8222-222222222227'),
        );

        $this->assertSame(104857600, $video->size());
    }

    /** size() возвращает null, если meta['size'] отсутствует. */
    public function testSizeReturnsNullWhenNotInMeta(): void
    {
        $video = Video::create(
            new VideoTitle('No size'),
            new FileExtension('mp4'),
            Uuid::fromString('22222222-2222-4222-8222-222222222228'),
        );

        $this->assertNull($video->size());
    }

    /** clearSourceKey() не имеет защиты assertNotDeleted — работает даже на удалённом видео. */
    public function testClearSourceKeyOnDeletedVideoDoesNotThrow(): void
    {
        $video = Video::reconstitute(
            new VideoTitle('Deleted source video'),
            new FileExtension('mp4'),
            Uuid::fromString('22222222-2222-4222-8222-222222222229'),
            ['sourceKey' => 'videos/original.mp4'],
            VideoDates::create(),
            Uuid::fromString('33333333-3333-4333-8333-333333333339'),
            true,
        );

        $video->clearSourceKey();

        $this->assertNull($video->meta()['sourceKey']);
    }

    /** markDeleted() с завершённой (COMPLETED) задачей завершается успешно. */
    public function testMarkDeletedSucceedsWhenOnlyCompletedTasksExist(): void
    {
        $video = Video::reconstitute(
            new VideoTitle('Completed tasks video'),
            new FileExtension('mp4'),
            Uuid::fromString('44444444-4444-4444-8444-444444444441'),
            [],
            VideoDates::create(),
            Uuid::fromString('44444444-4444-4444-8444-444444444442'),
        );

        // Завершённая задача не блокирует удаление
        $task = Task::reconstitute(
            Uuid::fromString('44444444-4444-4444-8444-444444444442'),
            Uuid::fromString('44444444-4444-4444-8444-444444444443'),
            Uuid::fromString('44444444-4444-4444-8444-444444444444'),
            \App\Domain\Video\ValueObject\TaskStatus::COMPLETED,
            new \App\Domain\Video\ValueObject\Progress(100),
            \App\Domain\Video\ValueObject\TaskDates::create(),
            Uuid::fromString('44444444-4444-4444-8444-444444444445'),
        );

        $video->markDeleted([$task]);

        $this->assertTrue($video->isDeleted());
    }
}
