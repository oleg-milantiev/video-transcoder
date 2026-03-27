<?php

namespace App\Application\QueryHandler;

use App\Application\DTO\PresetWithTaskDTO;
use App\Application\DTO\TaskInfoDTO;
use App\Application\DTO\VideoDetailsDTO;
use App\Application\Exception\QueryException;
use App\Application\Query\GetVideoDetailsQuery;
use App\Application\Query\Repository\VideoDetailsReadRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Infrastructure\Security\Voter\VideoAccessVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class GetVideoDetailsHandler
{
    public function __construct(
        private VideoRepositoryInterface $videoRepository,
        private VideoDetailsReadRepositoryInterface $videoDetailsReadRepository,
        private StorageInterface $storage,
        private Security $security,
    ) {}

    public function __invoke(GetVideoDetailsQuery $query): VideoDetailsDTO
    {
        $video = $this->videoRepository->findById($query->uuid);
        if (!$video) {
            throw new QueryException('Video not found');
        }

        if (!$this->security->isGranted(VideoAccessVoter::CAN_VIEW_DETAILS, $video)) {
            throw new QueryException('Access denied');
        }

        $presetsWithTasks = [];
        foreach ($this->videoDetailsReadRepository->getDetailsByVideoId($video->id()) as $presetData) {
            $taskDto = null;
            if ($presetData['task']) {
                $taskDto = new TaskInfoDTO(
                    status: TaskStatus::tryFrom((int)$presetData['task']['status'])?->name ?? 'UNKNOWN',
                    progress: $presetData['task']['progress'],
                    createdAt: $presetData['task']['createdAt'],
                    downloadFilename: $presetData['task']['downloadFilename'],
                    id: $presetData['task']['id'],
                );
            }
            $presetsWithTasks[] = new PresetWithTaskDTO(
                id: $presetData['id'],
                title: $presetData['title'],
                task: $taskDto,
            );
        }

        return VideoDetailsDTO::fromDomain($video, $presetsWithTasks, $this->storage);
    }
}
