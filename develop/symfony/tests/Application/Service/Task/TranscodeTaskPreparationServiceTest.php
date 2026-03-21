<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Task;

use App\Application\Service\Task\TranscodeTaskPreparationService;
use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\Bitrate;
use App\Domain\Video\ValueObject\Codec;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\PresetTitle;
use App\Domain\Video\ValueObject\Resolution;
use App\Domain\Video\ValueObject\VideoStatus;
use App\Domain\Video\ValueObject\VideoTitle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Uid\UuidV4;

class TranscodeTaskPreparationServiceTest extends TestCase
{
    public function testPrepareReturnsContextAndMarksTaskStarted(): void
    {
        $video = $this->createVideo(12.5);
        $preset = $this->createPreset(5);
        $task = $this->createTask($video->id(), $preset->id());

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(static fn (Task $savedTask): bool => $savedTask->status()->name === 'PROCESSING'));
        $taskRepository->expects($this->once())
            ->method('log')
            ->with(13, 'info', 'Transcoding started');

        $presetRepository = $this->createMock(PresetRepositoryInterface::class);
        $presetRepository->expects($this->once())
            ->method('findById')
            ->with(5)
            ->willReturn($preset);

        $storage = $this->createMock(StorageInterface::class);
        $storage->expects($this->exactly(2))
            ->method('getAbsolutePath')
            ->willReturnCallback(static function (string $path) use ($video): string {
                if ($path === sprintf('%s/5.mp4', $video->id()->toRfc4122())) {
                    return '/var/storage/' . $path;
                }

                if ($path === $video->getSrcFilename()) {
                    return '/var/storage/' . $path;
                }

                return '/var/storage/unknown';
            });

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->once())
            ->method('mkdir')
            ->with(sprintf('/var/storage/%s', $video->id()->toRfc4122()));

        $service = new TranscodeTaskPreparationService($presetRepository, $taskRepository, $storage, $filesystem);
        $context = $service->prepare($task, $video);

        $this->assertSame(sprintf('%s/5.mp4', $video->id()->toRfc4122()), $context->relativeOutputPath);
        $this->assertSame(sprintf('/var/storage/%s/5.mp4', $video->id()->toRfc4122()), $context->absoluteOutputPath);
        $this->assertSame('/var/storage/' . $video->getSrcFilename(), $context->inputPath);
        $this->assertSame($task, $context->task);
        $this->assertSame($video, $context->video);
        $this->assertSame($preset, $context->preset);
    }

    public function testPrepareLogsAndThrowsWhenPresetNotFound(): void
    {
        $video = $this->createVideo(10.0);
        $task = $this->createTask($video->id(), 99);

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())
            ->method('log')
            ->with(13, 'error', 'Preset not found for task');
        $taskRepository->expects($this->never())->method('save');

        $presetRepository = $this->createMock(PresetRepositoryInterface::class);
        $presetRepository->expects($this->once())
            ->method('findById')
            ->with(99)
            ->willReturn(null);

        $storage = $this->createStub(StorageInterface::class);
        $filesystem = $this->createStub(Filesystem::class);

        $service = new TranscodeTaskPreparationService($presetRepository, $taskRepository, $storage, $filesystem);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Preset not found for task');

        $service->prepare($task, $video);
    }

    private function createVideo(float $duration): Video
    {
        return Video::create(
            title: new VideoTitle('Clip'),
            extension: new FileExtension('mp4'),
            status: VideoStatus::UPLOADED,
            userId: 7,
            meta: ['duration' => $duration],
            id: UuidV4::fromString('123e4567-e89b-42d3-a456-426614174120'),
        );
    }

    private function createPreset(int $id): Preset
    {
        return new Preset(
            title: new PresetTitle('HD 720'),
            resolution: new Resolution(1280, 720),
            codec: new Codec('h264'),
            bitrate: new Bitrate(3.0),
            id: $id,
        );
    }

    private function createTask(UuidV4 $videoId, int $presetId): Task
    {
        $task = Task::create($videoId, $presetId, 7);
        $task->setId(13);

        return $task;
    }
}

