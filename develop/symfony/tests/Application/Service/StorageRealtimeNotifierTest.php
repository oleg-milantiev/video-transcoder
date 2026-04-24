<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use App\Application\Command\Mercure\PublishMercureMessage;
use App\Application\Service\Storage\StorageRealtimeNotifier;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Entity\Tariff;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\TariffStorageGb;
use App\Domain\Video\Repository\StorageRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class StorageRealtimeNotifierTest extends TestCase
{
    public function testNotifyStorageUpdatedDispatchesMessage(): void
    {
        $userId = Uuid::fromString('11111111-1111-4111-8111-111111111111');

        $tariff = $this->createStub(Tariff::class);
        $tariff->method('storageGb')->willReturn(new TariffStorageGb(1)); // 1 GB

        $user = $this->createStub(User::class);
        $user->method('tariff')->willReturn($tariff);

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($user);

        $storageRepository = $this->createStub(StorageRepositoryInterface::class);
        $storageRepository->method('getUsedStorageSize')->willReturn(150 * 1024 * 1024); // 150 MB

        $dispatched = [];
        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$dispatched): Envelope {
                $dispatched[] = $message;
                return new Envelope($message);
            });

        $notifier = new StorageRealtimeNotifier($commandBus, $userRepository, $storageRepository);
        $notifier->notifyStorageUpdated($userId);

        $this->assertCount(1, $dispatched);
        $this->assertInstanceOf(PublishMercureMessage::class, $dispatched[0]);

        $msg = $dispatched[0]->message;
        $this->assertSame('storage', $msg->entity);
        $this->assertSame('updated', $msg->action);
        $this->assertSame(150 * 1024 * 1024, $msg->payload['storageNow']);
        $this->assertSame(1 * 1024 * 1024 * 1024, $msg->payload['storageMax']);
    }

    public function testNotifyStorageUpdatedSkipsWhenUserNotFound(): void
    {
        $userId = Uuid::fromString('11111111-1111-4111-8111-111111111111');

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn(null);

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->never())->method('dispatch');

        $notifier = new StorageRealtimeNotifier(
            $commandBus,
            $userRepository,
            $this->createStub(StorageRepositoryInterface::class),
        );
        $notifier->notifyStorageUpdated($userId);
    }

    public function testNotifyStorageUpdatedSkipsWhenTariffNull(): void
    {
        $userId = Uuid::fromString('11111111-1111-4111-8111-111111111111');

        $user = $this->createStub(User::class);
        $user->method('tariff')->willReturn(null);

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($user);

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->never())->method('dispatch');

        $notifier = new StorageRealtimeNotifier(
            $commandBus,
            $userRepository,
            $this->createStub(StorageRepositoryInterface::class),
        );
        $notifier->notifyStorageUpdated($userId);
    }
}
