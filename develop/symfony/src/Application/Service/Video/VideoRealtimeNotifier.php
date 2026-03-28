<?php

declare(strict_types=1);

namespace App\Application\Service\Video;

use App\Application\Command\Mercure\PublishMercureMessage;
use App\Application\DTO\MercureMessageDTO;
use App\Application\DTO\VideoRealtimePayloadDTO;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class VideoRealtimeNotifier
{
    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
        private StorageInterface $storage,
        private TaskRepositoryInterface $taskRepository,
    ) {
    }

    /**
     * @param array<string,mixed> $extraPayload
     */
    public function notifyVideoUpdated(Video $video, string $action = 'updated', array $extraPayload = []): void
    {
        if ($video->id() === null) {
            return;
        }

        $hasPreview = ($video->meta()['preview'] ?? false) === true;
        $poster = $hasPreview ? $this->storage->publicUrl($this->storage->previewKey($video)) : null;
        $tasks = $this->taskRepository->findByVideoId($video->id());

        $dto = VideoRealtimePayloadDTO::fromVideo($video, $poster, $tasks);

        $this->commandBus->dispatch(new PublishMercureMessage(new MercureMessageDTO(
            action: $action,
            entity: 'video',
            id: $video->id(),
            userId: $video->userId(),
            payload: array_merge($dto->toArray(), $extraPayload),
        )));
    }
}
