<?php

declare(strict_types=1);

namespace App\Tests\Application\QueryHandler;

use App\Application\QueryHandler\QueryBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class QueryBusTest extends TestCase
{
    public function testQueryDelegatesToMessengerAndReturnsHandledResult(): void
    {
        $message = new \stdClass();
        $handledStamp = new HandledStamp('result', 'handler');
        $envelope = new Envelope($message, [$handledStamp]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($message)
            ->willReturn($envelope);

        $queryBus = new QueryBus($bus);

        $this->assertSame('result', $queryBus->query($message));
    }
}

