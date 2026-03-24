<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler\Video;

use App\Application\Command\Video\CleanupDeletedVideoMedia;
use App\Application\CommandHandler\Video\CleanupDeletedVideoMediaHandler;
use App\Application\Logging\LogServiceInterface;
use App\Application\Service\Task\DeletedTaskCleanupService;
use App\Application\Service\Video\DeletedVideoCleanupService;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskDates;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoTitle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV4;

final class CleanupDeletedVideoMediaHandlerTest extends TestCase
{
    public function testHandlerCleansVideoAndTasksForDeletedVideo(): void
    {
        $videoId = UuidV4::fromString('11111111-1111-4111-8111-111111111111');
        $userId = UuidV4::fromString('22222222-2222-4222-8222-222222222222');

        $video = Video::reconstitute(
            title: new VideoTitle('deleted'),
            extension: new FileExtension('mp4'),
            userId: $userId,
            meta: ['sourceKey' => 'src/deleted.mp4'],
            dates: VideoDates::create(),
            id: $videoId,
            deleted: true,
        );

        $task = Task::reconstitute(
            videoId: $videoId,
            presetId: UuidV4::fromString('33333333-3333-4333-8333-333333333333'),
            userId: $userId,
            status: TaskStatus::deleted(),
            progress: new Progress(100),
            dates: TaskDates::create(),
            id: UuidV4::fromString('44444444-4444-4444-8444-444444444444'),
            meta: ['output' => 'output/deleted.mp4'],
            deleted: true,
        );

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->once())
            ->method('findById')
            ->with($videoId)
            ->willReturn($video);

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())
            ->method('findByVideoId')
            ->with($videoId)
            ->willReturn([$task]);

        $storage = $this->createMock(StorageInterface::class);
        $storage->expects($this->exactly(2))
            ->method('delete')
            ->willReturnMap([
                ['src/deleted.mp4', true],
                ['output/deleted.mp4', true],
            ]);

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->exactly(2))->method('log');

        $videoCleanupService = new DeletedVideoCleanupService($videoRepository, $storage, $logService);
        $taskCleanupService = new DeletedTaskCleanupService($taskRepository, $storage, $logService);

        $handler = new CleanupDeletedVideoMediaHandler(
            $videoRepository,
            $videoCleanupService,
            $taskCleanupService,
        );

        $handler(new CleanupDeletedVideoMedia($videoId));
    }
}
