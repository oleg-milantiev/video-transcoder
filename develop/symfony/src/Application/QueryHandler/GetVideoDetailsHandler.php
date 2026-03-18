<?php

namespace App\Application\QueryHandler;

use App\Application\DTO\PresetWithTaskDTO;
use App\Application\DTO\TaskInfoDTO;
use App\Application\DTO\VideoDetailsDTO;
use App\Application\Exception\QueryException;
use App\Application\Query\GetVideoDetailsQuery;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\ValueObject\TaskStatus;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetVideoDetailsHandler
{
    public function __construct(
        private VideoRepositoryInterface $videoRepository,
        private Security $security,
    ) {}

    public function __invoke(GetVideoDetailsQuery $query): VideoDetailsDTO
    {
        $video = $this->videoRepository->findById($query->uuid);
        if (!$video) {
            throw new QueryException('Video not found');
        }

        // TODO voter with admin grants
        if ($video->userId() !== $this->security->getUser()->getId()) {
            throw new QueryException('Access denied');
        }

        $presetsWithTasks = [];
        foreach ($this->videoRepository->getDetails($video) as $presetData) {
            $taskDto = null;
            if ($presetData['task']) {
                $taskDto = new TaskInfoDTO(
                    status: TaskStatus::tryFrom((int)$presetData['task']['status'])?->name ?? 'UNKNOWN',
                    progress: $presetData['task']['progress'],
                    createdAt: $presetData['task']['createdAt'],
                    id: $presetData['task']['id'],
                );
            }
            $presetsWithTasks[] = new PresetWithTaskDTO(
                id: $presetData['id'],
                name: $presetData['name'],
                task: $taskDto,
            );
        }

        return VideoDetailsDTO::fromDomain($video, $presetsWithTasks);
    }
}
