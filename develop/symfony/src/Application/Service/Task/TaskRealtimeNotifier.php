<?php

declare(strict_types=1);

namespace App\Application\Service\Task;

use App\Application\Command\Mercure\PublishMercureMessage;
use App\Application\DTO\MercureMessageDTO;
use App\Domain\Video\Entity\Task;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class TaskRealtimeNotifier
{
    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
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

        $payload = array_merge([
            'taskId' => $task->id()->toRfc4122(),
            'videoId' => $task->videoId()->toRfc4122(),
            'presetId' => $task->presetId()->toRfc4122(),
            'status' => $task->status()->name,
            'progress' => $task->progress()->value(),
            'createdAt' => $task->createdAt()->format('Y-m-d H:i'),
            'updatedAt' => $task->updatedAt()?->format('Y-m-d H:i'),
        ], $extraPayload);

        $this->commandBus->dispatch(new PublishMercureMessage(new MercureMessageDTO(
            action: $action,
            entity: 'task',
            id: $task->userId(),
            payload: $payload,
        )));
    }
}
