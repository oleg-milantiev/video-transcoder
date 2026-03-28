<?php

declare(strict_types=1);

namespace App\Application\Service\Task;

use App\Application\Command\Mercure\PublishMercureMessage;
use App\Application\DTO\MercureMessageDTO;
use App\Application\DTO\TaskRealtimePayloadDTO;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class TaskRealtimeNotifier
{
    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
        private PresetRepositoryInterface $presetRepository,
        private VideoRepositoryInterface $videoRepository,
    ) {
    }

    public function notifyTaskUpdated(Task $task, string $action = 'updated', array $extraPayload = []): void
    {
        if ($task->id() === null) {
            return;
        }

        $dto = TaskRealtimePayloadDTO::fromTask($task);

        $video = $this->videoRepository->findById($task->videoId());
        $preset = $this->presetRepository->findById($task->presetId());
        $dto->addVideoPresetFields($video, $preset);

        $this->commandBus->dispatch(new PublishMercureMessage(new MercureMessageDTO(
            action: $action,
            entity: 'task',
            id: $task->id(),
            userId: $task->userId(),
            payload: array_merge($dto->toArray(), $extraPayload),
        )));
    }
}
