<?php

declare(strict_types=1);

namespace App\Tests\Domain\Video\Entity;

use App\Domain\Video\Entity\Video;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoTitle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV4;

final class VideoTest extends TestCase
{
    public function testCreateInitializesAllFields(): void
    {
        $id = UuidV4::fromString('99999999-9999-4999-8999-999999999999');
        $createdAt = new \DateTimeImmutable('2026-03-19 12:00:00');

        $video = Video::reconstitute(
            title: new VideoTitle('Trailer'),
            extension: new FileExtension('mp4'),
            userId: UuidV4::fromString('77777777-7777-4777-8777-777777777777'),
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
            UuidV4::fromString('55555555-5555-4555-8555-555555555550'),
        );

        $this->assertNull($video->id());
        $this->assertNotNull($video->createdAt());
    }

    public function testUpdateMetaMergesTopLevelKeysAndSetsUpdatedAt(): void
    {
        $video = Video::reconstitute(
            title: new VideoTitle('Meta merge'),
            extension: new FileExtension('mkv'),
            userId: UuidV4::fromString('22222222-2222-4222-8222-222222222220'),
            meta: ['duration' => 100.2, 'quality' => 'hd'],
            dates: VideoDates::create(new \DateTimeImmutable('2026-03-19 10:00:00')),
            id: UuidV4::fromString('11111111-1111-4111-8111-111111111111'),
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
            UuidV4::fromString('22222222-2222-4222-8222-222222222221'),
            ['duration' => 50.0],
            VideoDates::create(new \DateTimeImmutable('2026-03-19 10:00:00')),
            UuidV4::fromString('22222222-2222-4222-8222-222222222222'),
        );

        $video->updateMeta(['duration' => 55.7]);

        $this->assertSame(55.7, $video->duration());
    }

    public function testGetSrcFilenameReturnsUuidWithExtension(): void
    {
        $id = UuidV4::fromString('33333333-3333-4333-8333-333333333333');
        $video = Video::reconstitute(
            new VideoTitle('Source file'),
            new FileExtension('avi'),
            UuidV4::fromString('88888888-8888-4888-8888-888888888888'),
            [],
            VideoDates::create(),
            $id,
        );

        $this->assertSame($id->toRfc4122() . '.avi', $video->getSrcFilename());
    }

    public function testGetPosterReturnsFilenameWhenPreviewExists(): void
    {
        $id = UuidV4::fromString('44444444-4444-4444-8444-444444444444');
        $video = Video::reconstitute(
            new VideoTitle('Poster test'),
            new FileExtension('mp4'),
            UuidV4::fromString('99999999-9999-4999-8999-999999999998'),
            ['preview' => true],
            VideoDates::create(),
            $id,
        );

        $this->assertSame($id->toRfc4122() . '.jpg', $video->getPoster());
    }

    public function testGetPosterReturnsNullWhenPreviewMissing(): void
    {
        $video = Video::reconstitute(
            new VideoTitle('No poster'),
            new FileExtension('mp4'),
            UuidV4::fromString('99999999-9999-4999-8999-999999999997'),
            ['preview' => false],
            VideoDates::create(),
            UuidV4::fromString('55555555-5555-4555-8555-555555555555'),
        );

        $this->assertNull($video->getPoster());
    }
}

