<?php
declare(strict_types=1);

namespace App\Application\CommandHandler\Video;

use App\Application\Command\Video\CleanupDeletedVideoMedia;
use App\Application\Service\Task\DeletedTaskCleanupService;
use App\Application\Service\Video\DeletedVideoCleanupService;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class CleanupDeletedVideoMediaHandler
{
    public function __construct(
        private VideoRepositoryInterface $videoRepository,
        private DeletedVideoCleanupService $deletedVideoCleanupService,
        private DeletedTaskCleanupService $deletedTaskCleanupService,
    ) {
    }

    public function __invoke(CleanupDeletedVideoMedia $command): void
    {
        $video = $this->videoRepository->findById($command->videoId);
        if ($video !== null && $video->isDeleted()) {
            $this->deletedVideoCleanupService->cleanupVideo($video);
            $this->deletedTaskCleanupService->cleanupByVideoId($command->videoId);
        }
    }
}
