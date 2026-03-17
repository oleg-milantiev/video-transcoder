<?php

namespace App\Application\Query;

use App\Application\DTO\TaskItemDTO;
use App\Application\Exception\QueryException;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class StartTranscodeHandler
{
    public function __construct(
        private VideoRepositoryInterface $videoRepository,
        private PresetRepositoryInterface $presetRepository,
        private TaskRepositoryInterface $taskRepository,
        private UserRepositoryInterface $userRepository,
    ) {}

    public function __invoke(StartTranscodeQuery $query): TaskItemDTO
    {
        $video = $this->videoRepository->findById($query->uuid);
        if (!$video) {
            throw new QueryException('Video not found');
        }

        $user = $this->userRepository->findById($query->userId);
        if (!$user) {
            throw new QueryException('User not found');
        }

        if ($video->userId() !== $user->id()) {
            throw new QueryException('Access denied');
        }

        $preset = $this->presetRepository->findById($query->presetId);
        if (!$preset) {
            throw new QueryException('Preset not found');
        }

        try {
            $task = Task::create($video, $preset, $user);
            $this->taskRepository->save($task);
        } catch (\Throwable $e) {
            throw new QueryException('Failed to create task: ' . $e->getMessage());
        }

        return TaskItemDTO::fromDomain($task);
    }
}

