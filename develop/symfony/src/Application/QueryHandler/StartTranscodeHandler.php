<?php

namespace App\Application\QueryHandler;

use App\Application\Command\Task\StartTaskScheduler;
use App\Application\DTO\TaskItemDTO;
use App\Application\Event\StartTranscodeFail;
use App\Application\Event\StartTranscodeStart;
use App\Application\Event\StartTranscodeSuccess;
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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class StartTranscodeHandler
{
    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
        #[Autowire(service: 'messenger.bus.event')]
        private MessageBusInterface $eventBus,
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
        $this->eventBus->dispatch(new StartTranscodeStart($query->uuid, $query->presetId, $query->userId));

        $video = $this->videoRepository->findById($query->uuid);
        if (!$video) {
            $this->eventBus->dispatch(new StartTranscodeFail('Video not found', $query->uuid, $query->presetId, $query->userId));
            throw new VideoNotFoundException('Video not found');
        }

        $user = $this->userRepository->findById($query->userId);
        if (!$user) {
            $this->eventBus->dispatch(new StartTranscodeFail('User not found', $query->uuid, $query->presetId, $query->userId));
            throw new UserNotFoundException('User not found');
        }

        if (!$this->security->isGranted(VideoAccessVoter::CAN_START_TRANSCODE, $video)) {
            $this->eventBus->dispatch(new StartTranscodeFail('Access denied', $query->uuid, $query->presetId, $query->userId));
            throw new TranscodeAccessDeniedException('Access denied');
        }

        $preset = $this->presetRepository->findById($query->presetId);
        if (!$preset) {
            $this->eventBus->dispatch(new StartTranscodeFail('Preset not found', $query->uuid, $query->presetId, $query->userId));
            throw new PresetNotFoundException('Preset not found');
        }

        try {
            $task = $this->taskRepository->findForTranscode($video->id(), $preset->id(), $user->id());

            if ($task instanceof Task) {
                $task->restart();
            } else {
                $task = Task::create($video->id(), $preset->id(), $user->id());
            }

            $this->taskRepository->save($task);
        } catch (\Throwable $e) {
            $this->eventBus->dispatch(new StartTranscodeFail('Failed to create task', $query->uuid, $query->presetId, $query->userId));
            throw new TaskCreationFailedException('Failed to create task', previous: $e);
        }

        $this->commandBus->dispatch(new StartTaskScheduler());
        $this->eventBus->dispatch(new StartTranscodeSuccess($task->id(), $query->uuid, $query->presetId, $query->userId));

        return TaskItemDTO::fromDomain($task, $video, $preset);
    }
}

