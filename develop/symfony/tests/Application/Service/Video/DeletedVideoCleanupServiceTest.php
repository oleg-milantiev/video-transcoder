<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Video;

use App\Application\Logging\LogServiceInterface;
use App\Application\Service\Video\DeletedVideoCleanupService;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoTitle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV4;

final class DeletedVideoCleanupServiceTest extends TestCase
{
    public function testCleanupUsesSourceKeyFromMeta(): void
    {
        $userId = UuidV4::fromString('11111111-1111-4111-8111-111111111111');
        $videoId = UuidV4::fromString('22222222-2222-4222-8222-222222222222');

        $video = Video::reconstitute(
            title: new VideoTitle('Cleanup me'),
            extension: new FileExtension('mp4'),
            userId: $userId,
            meta: ['sourceKey' => 'src/cleanup.mp4'],
            dates: VideoDates::create(),
            id: $videoId,
            deleted: true,
        );

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->once())
            ->method('findDeletedVideoForCleanup')
            ->with(100)
            ->willReturn([$video]);

        $storage = $this->createMock(StorageInterface::class);
        $storage->expects($this->once())
            ->method('delete')
            ->with('src/cleanup.mp4')
            ->willReturn(true);

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())->method('log');

        $service = new DeletedVideoCleanupService($videoRepository, $storage, $logService);

        $result = $service->cleanup();

        $this->assertSame(['candidates' => 1, 'filesDeleted' => 1], $result);
    }
}

