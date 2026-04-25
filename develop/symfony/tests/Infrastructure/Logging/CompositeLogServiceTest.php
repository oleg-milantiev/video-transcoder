<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Logging;

use App\Application\Logging\LogServiceInterface;
use App\Domain\Shared\ValueObject\Uuid;
use App\Infrastructure\Logging\CompositeLogService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class CompositeLogServiceTest extends TestCase
{
    public function testDelegatesToAllLoggers(): void
    {
        $objectId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
        $calls = [];

        $logger1 = $this->createMock(LogServiceInterface::class);
        $logger1->expects($this->once())
            ->method('log')
            ->willReturnCallback(static function () use (&$calls): void { $calls[] = 'logger1'; });

        $logger2 = $this->createMock(LogServiceInterface::class);
        $logger2->expects($this->once())
            ->method('log')
            ->willReturnCallback(static function () use (&$calls): void { $calls[] = 'logger2'; });

        $composite = new CompositeLogService([$logger1, $logger2]);
        $composite->log('video', 'create', $objectId, LogLevel::INFO, 'Video created', ['videoId' => 'abc']);

        self::assertSame(['logger1', 'logger2'], $calls);
    }

    public function testDelegatesExactArgumentsToEachLogger(): void
    {
        $objectId = Uuid::fromString('22222222-2222-4222-8222-222222222222');
        $context = ['userId' => 'xyz'];

        $logger = $this->createMock(LogServiceInterface::class);
        $logger->expects($this->once())
            ->method('log')
            ->with('task', 'transcode', $objectId, LogLevel::WARNING, 'Task warn', $context);

        $composite = new CompositeLogService([$logger]);
        $composite->log('task', 'transcode', $objectId, LogLevel::WARNING, 'Task warn', $context);
    }

    public function testWorksWithEmptyLoggers(): void
    {
        $composite = new CompositeLogService([]);
        // No exception expected
        $composite->log('x', 'y', null, LogLevel::DEBUG, 'msg');
        $this->addToAssertionCount(1);
    }

    public function testPassesNullObjectId(): void
    {
        $logger = $this->createMock(LogServiceInterface::class);
        $logger->expects($this->once())
            ->method('log')
            ->with('admin', 'login', null, LogLevel::INFO, 'Login', []);

        $composite = new CompositeLogService([$logger]);
        $composite->log('admin', 'login', null, LogLevel::INFO, 'Login');
    }
}
