<?php

namespace App\Application\Query;

use App\Application\DTO\PresetWithTaskDTO;
use App\Application\DTO\TaskInfoDTO;
use App\Application\DTO\VideoDetailsDTO;
use App\Application\Exception\QueryException;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\ValueObject\TaskStatus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetVideoDetailsHandler
{
    public function __construct(
        private VideoRepositoryInterface $videoRepository,
    ) {}

    public function __invoke(GetVideoDetailsQuery $query): VideoDetailsDTO
    {
        $details = $this->videoRepository->getDetails($query->uuid);
        if (!$details) {
            throw new QueryException('Video not found');
        }

        $video = $details['video'];
        $presetsWithTasks = [];

        foreach ($details['presetsWithTasks'] as $presetData) {
            $taskDto = null;
            if ($presetData['task']) {
                $taskDto = new TaskInfoDTO(
                    status: TaskStatus::tryFrom((int)$presetData['task']['status'])?->name ?? 'UNKNOWN',
                    progress: $presetData['task']['progress'],
                    createdAt: $presetData['task']['createdAt'],
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
