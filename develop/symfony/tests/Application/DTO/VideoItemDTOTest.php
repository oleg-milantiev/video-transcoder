<?php

declare(strict_types=1);

namespace App\Tests\Application\DTO;

use App\Application\DTO\VideoItemDTO;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoTitle;
use PHPUnit\Framework\TestCase;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskDates;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Domain\Shared\ValueObject\Uuid;

class VideoItemDTOTest extends TestCase
{
    public function testFromDomainMapsAllFields(): void
    {
        $uuid = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $video = Video::reconstitute(
            new VideoTitle('Demo Video'),
            new FileExtension('mp4'),
            Uuid::fromString('42424242-4242-4242-8242-424242424242'),
            ['preview' => true],
            VideoDates::create(new \DateTimeImmutable('2026-03-18 10:15:00')),
            $uuid,
        );

        $storage = $this->createStub(StorageInterface::class);
        $storage->method('previewKey')->willReturn($uuid->toRfc4122() . '.jpg');
        $storage->method('publicUrl')->willReturn('/uploads/' . $uuid->toRfc4122() . '.jpg');

        $dto = VideoItemDTO::fromDomain($video, $storage, $this->createStub(TaskRepositoryInterface::class));

        $this->assertSame($uuid->toRfc4122(), $dto->uuid);
        $this->assertSame('Demo Video', $dto->title);
        $this->assertSame('2026-03-18 10:15', $dto->createdAt);
        $this->assertFalse($dto->deleted);
        $this->assertSame('/uploads/' . $uuid->toRfc4122() . '.jpg', $dto->poster);
    }

    public function testFromDomainMapsDeletedVideo(): void
    {
        $uuid = Uuid::fromString('99999999-9999-4999-8999-999999999999');
        $video = Video::reconstitute(
            new VideoTitle('Removed Video'),
            new FileExtension('mp4'),
            Uuid::fromString('42424242-4242-4242-8242-424242424242'),
            ['preview' => true],
            VideoDates::create(new \DateTimeImmutable('2026-03-18 10:15:00')),
            $uuid,
            true,
        );

        $storage = $this->createStub(StorageInterface::class);
        $storage->method('previewKey')->willReturn($uuid->toRfc4122() . '.jpg');
        $storage->method('publicUrl')->willReturn('/uploads/' . $uuid->toRfc4122() . '.jpg');

        $dto = VideoItemDTO::fromDomain($video, $storage, $this->createStub(TaskRepositoryInterface::class));

        $this->assertTrue($dto->deleted);
        $this->assertSame('/uploads/' . $uuid->toRfc4122() . '.jpg', $dto->poster);
    }

    public function testFromDomainWithNoPosterWhenNoPreview(): void
    {
        $uuid = Uuid::fromString('33333333-3333-4333-8333-333333333333');
        $video = Video::reconstitute(
            new VideoTitle('No Preview'),
            new FileExtension('mp4'),
            Uuid::fromString('42424242-4242-4242-8242-424242424242'),
            [],  // no preview meta
            VideoDates::create(new \DateTimeImmutable('2026-03-18 10:00:00')),
            $uuid,
        );

        $dto = VideoItemDTO::fromDomain($video, $this->createStub(StorageInterface::class), $this->createStub(TaskRepositoryInterface::class));

        $this->assertNull($dto->poster);
    }

    public function testFromDomainCanBeDeletedFalseWhenActiveTask(): void
    {
        $uuid = Uuid::fromString('44444444-4444-4444-8444-444444444444');
        $video = Video::reconstitute(
            new VideoTitle('Active Video'),
            new FileExtension('mp4'),
            Uuid::fromString('42424242-4242-4242-8242-424242424242'),
            [],
            VideoDates::create(),
            $uuid,
        );

        $activeTask = Task::reconstitute(
            videoId: $uuid,
            presetId: Uuid::fromString('55555555-5555-4555-8555-555555555555'),
            userId: Uuid::fromString('42424242-4242-4242-8242-424242424242'),
            status: TaskStatus::PENDING,
            progress: new Progress(0),
            dates: TaskDates::create(),
            id: Uuid::fromString('66666666-6666-4666-8666-666666666666'),
        );

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->method('findByVideoId')->with($uuid)->willReturn([$activeTask]);

        $dto = VideoItemDTO::fromDomain($video, $this->createStub(StorageInterface::class), $taskRepository);

        $this->assertFalse($dto->canBeDeleted);
    }

    public function testFromDomainCanBeDeletedTrueWhenAllTasksDeleted(): void
    {
        $uuid = Uuid::fromString('77777777-7777-4777-8777-777777777777');
        $video = Video::reconstitute(
            new VideoTitle('Deletable Video'),
            new FileExtension('mp4'),
            Uuid::fromString('42424242-4242-4242-8242-424242424242'),
            [],
            VideoDates::create(),
            $uuid,
        );

        $deletedTask1 = Task::reconstitute(
            videoId: $uuid,
            presetId: Uuid::fromString('55555555-5555-4555-8555-555555555555'),
            userId: Uuid::fromString('42424242-4242-4242-8242-424242424242'),
            status: TaskStatus::DELETED,
            progress: new Progress(0),
            dates: TaskDates::create(),
            id: Uuid::fromString('66666666-6666-4666-8666-666666666661'),
            deleted: true,
        );

        $deletedTask2 = Task::reconstitute(
            videoId: $uuid,
            presetId: Uuid::fromString('55555555-5555-4555-8555-555555555556'),
            userId: Uuid::fromString('42424242-4242-4242-8242-424242424242'),
            status: TaskStatus::DELETED,
            progress: new Progress(0),
            dates: TaskDates::create(),
            id: Uuid::fromString('66666666-6666-4666-8666-666666666662'),
            deleted: true,
        );

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->method('findByVideoId')->with($uuid)->willReturn([$deletedTask1, $deletedTask2]);

        $dto = VideoItemDTO::fromDomain($video, $this->createStub(StorageInterface::class), $taskRepository);

        $this->assertTrue($dto->canBeDeleted);
    }
}
