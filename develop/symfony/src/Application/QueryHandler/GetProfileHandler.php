<?php
declare(strict_types=1);

namespace App\Application\QueryHandler;

use App\Application\DTO\ProfileDTO;
use App\Application\Query\GetProfileQuery;
use App\Domain\Video\Repository\StorageRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\ValueObject\TaskStatus;
use DateTimeInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class GetProfileHandler
{
    public function __construct(
        private StorageRepositoryInterface $storageRepository,
        private TaskRepositoryInterface $taskRepository,
        private VideoRepositoryInterface $videoRepository,
    ) {
    }

    public function __invoke(GetProfileQuery $query): ProfileDTO
    {
        $taskCountByStatus = $this->taskRepository->getActiveCountByStatus($query->userId);

        return ProfileDTO::create(
            storageDelete24: $this->storageRepository->getDeletedIn24hSize($query->userId),
            videoCountActive: $this->videoRepository->getActiveCount($query->userId),
            videoCountTotal: $this->videoRepository->getTotalCount($query->userId),
            taskCountActive: $this->taskRepository->getActiveCount($query->userId),
            taskCountTotal: $this->taskRepository->getTotalCount($query->userId),
            taskCountByStatus: $taskCountByStatus,
            willStartAt: isset($taskCountByStatus[TaskStatus::PENDING->value])
                ? $this->taskRepository->getFirstPendingTaskWillStartAt($query->userId)?->format(
                    DateTimeInterface::ATOM
                )
                : '',
        );
    }
}
