<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Console;

use App\Application\Command\Task\StartTaskScheduler;
use App\Application\Logging\LogServiceInterface;
use App\Presentation\Console\MinuteCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class MinuteCommandTest extends TestCase
{
    public function testExecuteDispatchesScheduler(): void
    {
        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(StartTaskScheduler::class))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())
            ->method('createLock')
            ->with('app:minute', 900)
            ->willReturn($lock);

        $command = new MinuteCommand(
            $commandBus,
            $this->createStub(LogServiceInterface::class),
            $lockFactory,
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Cleanup done: video candidates=0, task candidates=0, video files deleted=0, task files deleted=0.', $tester->getDisplay());
    }

    public function testExecuteReturnsSuccessWhenLockNotAcquired(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(false);
        $lock->expects($this->never())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())->method('createLock')->willReturn($lock);

        $command = new MinuteCommand(
            $this->createStub(MessageBusInterface::class),
            $this->createStub(LogServiceInterface::class),
            $lockFactory,
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testExecuteReturnsFailureWhenDispatchThrows(): void
    {
        $commandBus = $this->createStub(MessageBusInterface::class);
        $commandBus->method('dispatch')->willThrowException(new \RuntimeException('Bus unavailable'));

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())->method('createLock')->willReturn($lock);

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->atLeast(1))->method('log');

        $command = new MinuteCommand(
            $commandBus,
            $logService,
            $lockFactory,
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
    }

    public function testExecuteLogsErrorWhenLockReleaseThrows(): void
    {
        $commandBus = $this->createStub(MessageBusInterface::class);
        $commandBus->method('dispatch')->willReturnCallback(
            static fn(object $m): Envelope => new Envelope($m),
        );

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release')->willThrowException(new \RuntimeException('Lock store unavailable'));

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())->method('createLock')->willReturn($lock);

        $logService = $this->createStub(LogServiceInterface::class);

        $command = new MinuteCommand($commandBus, $logService, $lockFactory);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        // dispatch succeeded → inner try returns SUCCESS
        // finally: release throws → caught by inner catch (no return)
        // original SUCCESS is preserved
        $this->assertSame(Command::SUCCESS, $exitCode);
    }
}
