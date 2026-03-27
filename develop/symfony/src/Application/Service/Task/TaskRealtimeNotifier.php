<?php

declare(strict_types=1);

namespace App\Application\Service\Task;

use App\Application\Command\Mercure\PublishMercureMessage;
use App\Application\DTO\MercureMessageDTO;
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

    /**
     * @param array<string, mixed> $extraPayload
     */
    public function notifyTaskUpdated(Task $task, string $action = 'updated', array $extraPayload = []): void
    {
        if ($task->id() === null) {
            return;
        }

        $video = $this->videoRepository->findById($task->videoId());
        $preset = $this->presetRepository->findById($task->presetId());

        // todo sync realtime contract with frontend
        $payload = array_merge([
            'taskId' => $task->id()->toRfc4122(),
            'videoId' => $task->videoId()->toRfc4122(),
            'presetId' => $task->presetId()->toRfc4122(),
            'status' => $task->status()->name,
            'progress' => $task->progress()->value(),
            'videoTitle' => $video?->title()->value(),
            'downloadFilename' => $video?->title()->value() . ' - ' . $preset?->title()->value(),
            'createdAt' => $task->createdAt()->format('Y-m-d H:i'),
            'updatedAt' => $task->updatedAt()?->format('Y-m-d H:i'),
        ], $extraPayload);

        $this->commandBus->dispatch(new PublishMercureMessage(new MercureMessageDTO(
            action: $action,
            entity: 'task',
            id: $task->id(),
            userId: $task->userId(),
            payload: $payload,
        )));
    }
}
