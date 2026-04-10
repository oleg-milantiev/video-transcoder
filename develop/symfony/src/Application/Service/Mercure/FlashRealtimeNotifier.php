<?php
declare(strict_types=1);

namespace App\Application\Service\Mercure;

use App\Application\Command\Mercure\PublishMercureMessage;
use App\Application\DTO\FlashNotificationDTO;
use App\Application\DTO\MercureMessageDTO;
use App\Domain\Shared\ValueObject\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class FlashRealtimeNotifier
{
    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
    ) {
    }

    /**
     * @param array<string,mixed> $extraPayload
     */
    public function notify(Uuid $userId, FlashNotificationDTO $flash): void
    {
        $this->commandBus->dispatch(new PublishMercureMessage(new MercureMessageDTO(
            action: 'notify',
            entity: 'flash',
            id: null,
            userId: $userId,
            payload: $flash->toArray(),
        )));
    }
}
