<?php
declare(strict_types=1);

namespace App\Application\Service\Storage;

use App\Application\Command\Mercure\PublishMercureMessage;
use App\Application\DTO\MercureMessageDTO;
use App\Application\DTO\StorageRealtimePayloadDTO;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\Video\Repository\StorageRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class StorageRealtimeNotifier
{
    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
        private UserRepositoryInterface $userRepository,
        private StorageRepositoryInterface $storageRepository,
    ) {
    }

    public function notifyStorageUpdated(Uuid $userId): void
    {
        $user = $this->userRepository->findById($userId);

        if ($user === null || $user->tariff() === null) {
            return;
        }

        $storageNow = $this->storageRepository->getUsedStorageSize($userId);
        $storageMax = (int)($user->tariff()->storageGb()->value() * 1024 * 1024 * 1024);

        $dto = StorageRealtimePayloadDTO::fromSizes($storageNow, $storageMax);

        $this->commandBus->dispatch(
            new PublishMercureMessage(
                new MercureMessageDTO(
                    action: 'updated',
                    entity: 'storage',
                    id: $userId,
                    userId: $userId,
                    payload: $dto->toArray(),
                )
            )
        );
    }
}
