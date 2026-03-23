<?php

declare(strict_types=1);

namespace App\Application\Service\Video;

use App\Application\Command\Mercure\PublishMercureMessage;
use App\Application\DTO\MercureMessageDTO;
use App\Domain\Video\Entity\Video;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class VideoRealtimeNotifier
{
    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
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

        $payload = array_merge([
            'videoId' => $video->id()->toRfc4122(),
            'poster' => $video->getPoster(),
            'meta' => $video->meta(),
            'createdAt' => $video->createdAt()->format(DATE_ATOM),
            'updatedAt' => $video->updatedAt()?->format(DATE_ATOM),
        ], $extraPayload);

        $this->commandBus->dispatch(new PublishMercureMessage(new MercureMessageDTO(
            action: $action,
            entity: 'video',
            id: $video->id(),
            userId: $video->userId(),
            payload: $payload,
        )));
    }
}

