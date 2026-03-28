<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler\Mercure;

use App\Application\Command\Mercure\PublishMercureMessage;
use App\Application\CommandHandler\Mercure\PublishMercureMessageHandler;
use App\Application\DTO\MercureMessageDTO;
use App\Application\Service\Mercure\MercurePublisherInterface;
use App\Domain\Shared\ValueObject\Uuid;
use PHPUnit\Framework\TestCase;

final class PublishMercureMessageHandlerTest extends TestCase
{
    public function testInvokePublishesMessage(): void
    {
        $message = new MercureMessageDTO(
            action: 'updated',
            entity: 'task',
            id: Uuid::generate(),
            userId: Uuid::generate(),
            payload: ['status' => 'COMPLETED'],
        );
        $command = new PublishMercureMessage($message);

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->once())
            ->method('publish')
            ->with($message);

        $handler = new PublishMercureMessageHandler($publisher);
        $handler($command);
    }
}
