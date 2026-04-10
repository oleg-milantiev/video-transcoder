<?php
declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Command\Mercure\PublishMercureMessage;
use App\Application\DTO\MercureMessageDTO;
use App\Application\DTO\StorageRealtimePayloadDTO;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class StorageRealtimeNotifier implements StorageRealtimeNotifierInterface
{
    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
        private UserRepositoryInterface $userRepository,
        private VideoRepositoryInterface $videoRepository,
        private TaskRepositoryInterface $taskRepository,
    ) {
    }

    public function notifyStorageUpdated(Uuid $userId): void
    {
        $user = $this->userRepository->findById($userId);

        if ($user === null || $user->tariff() === null) {
            return;
        }

        $storageNow = $this->videoRepository->getStorageSize($userId)
            + $this->taskRepository->getStorageSize($userId);

        $storageMax = (int) ($user->tariff()->storageGb()->value() * 1024 * 1024 * 1024);

        $dto = StorageRealtimePayloadDTO::fromSizes($storageNow, $storageMax);

        $this->commandBus->dispatch(new PublishMercureMessage(new MercureMessageDTO(
            action: 'updated',
            entity: 'storage',
            id: $userId,
            userId: $userId,
            payload: $dto->toArray(),
        )));
    }
}
