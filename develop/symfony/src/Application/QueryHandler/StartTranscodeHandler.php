<?php

namespace App\Application\QueryHandler;

use App\Application\Command\Task\StartTaskScheduler;
use App\Application\DTO\TaskItemDTO;
use App\Application\Exception\PresetNotFoundException;
use App\Application\Exception\TaskCreationFailedException;
use App\Application\Exception\TranscodeAccessDeniedException;
use App\Application\Exception\UserNotFoundException;
use App\Application\Exception\VideoNotFoundException;
use App\Application\Query\StartTranscodeQuery;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Infrastructure\Security\Voter\VideoAccessVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class StartTranscodeHandler
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private VideoRepositoryInterface $videoRepository,
        private PresetRepositoryInterface $presetRepository,
        private TaskRepositoryInterface $taskRepository,
        private UserRepositoryInterface $userRepository,
        private Security $security,
    ) {}

    /**
     * @throws ExceptionInterface
     */
    public function __invoke(StartTranscodeQuery $query): TaskItemDTO
    {
        $video = $this->videoRepository->findById($query->uuid);
        if (!$video) {
            throw new VideoNotFoundException('Video not found');
        }

        $user = $this->userRepository->findById($query->userId);
        if (!$user) {
            throw new UserNotFoundException('User not found');
        }

        if (!$this->security->isGranted(VideoAccessVoter::CAN_START_TRANSCODE, $video)) {
            throw new TranscodeAccessDeniedException('Access denied');
        }

        $preset = $this->presetRepository->findById($query->presetId);
        if (!$preset) {
            throw new PresetNotFoundException('Preset not found');
        }

        try {
            $task = Task::create($video->id(), $preset->id(), $user->id());
            $this->taskRepository->save($task);
        } catch (\Throwable $e) {
            throw new TaskCreationFailedException('Failed to create task', previous: $e);
        }

        $this->messageBus->dispatch(new StartTaskScheduler());

        return TaskItemDTO::fromDomain($task, $video, $preset);
    }
}

